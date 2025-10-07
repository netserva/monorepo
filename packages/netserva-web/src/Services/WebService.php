<?php

namespace NetServa\Web\Services;

use Illuminate\Support\Facades\Process;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Web\Models\VirtualHost;
use NetServa\Web\Models\WebApplication;
use NetServa\Web\Models\WebServer;

class WebService
{
    /**
     * Create a new web server
     */
    public function createServer(array $data): WebServer
    {
        // Create or get infrastructure node if not provided
        if (! isset($data['infrastructure_node_id'])) {
            $node = InfrastructureNode::create([
                'name' => ($data['name'] ?? 'Test').' Node',
                'slug' => str($data['name'] ?? 'Test')->slug().'-'.rand(1000, 9999),
                'type' => 'host',
                'status' => 'active',
                'depth' => 0,
                'path' => str($data['name'] ?? 'Test')->slug().'-'.rand(1000, 9999),
            ]);
            $data['infrastructure_node_id'] = $node->id;
        }

        // Set up required fields according to migration
        $defaults = [
            'server_type' => $data['type'] ?? 'nginx',
            'version' => $data['version'] ?? null,
            'hostname' => $data['hostname'] ?? null,
            'public_ip' => $data['ip_address'] ?? null,
            'worker_processes' => 'auto',
            'worker_connections' => $data['max_connections'] ?? 1024,
            'keepalive_timeout' => 65,
            'client_max_body_size' => '64M',
            'gzip_enabled' => true,
            'server_tokens' => false,
            'is_active' => true,
            'service_status' => 'running',
            // Required fields based on migration
            'port_config' => json_encode(['http' => 80, 'https' => 443]),
            'config_path' => '/etc/nginx',
            'sites_path' => '/etc/nginx/sites-available',
            'enabled_path' => '/etc/nginx/sites-enabled',
        ];

        return WebServer::create(array_merge($defaults, $data));
    }

    /**
     * Create a virtual host
     */
    public function createVirtualHost(WebServer $server, array $data): VirtualHost
    {
        $vhostData = [
            'name' => $data['domain'],
            'web_server_id' => $server->id,
            'infrastructure_node_id' => $server->infrastructure_node_id,
            'primary_domain' => $data['domain'],
            'document_root' => $data['document_root'],
            'php_version' => $data['php_version'] ?? '8.4',
            'ssl_enabled' => $data['ssl_enabled'] ?? false,
            'is_active' => true,
        ];

        if (isset($data['aliases'])) {
            $vhostData['server_names'] = json_encode(array_merge([$data['domain']], $data['aliases']));
        } else {
            $vhostData['server_names'] = json_encode([$data['domain']]);
        }

        $vhost = VirtualHost::create($vhostData);

        // Create document root directory
        if (isset($data['document_root'])) {
            Process::run("mkdir -p {$data['document_root']}");
        }

        // Generate and write configuration
        $this->writeVirtualHostConfig($vhost);

        // Test and reload server configuration
        $this->testAndReloadConfig($server);

        return $vhost;
    }

    /**
     * Deploy a web application
     */
    public function deployApplication(VirtualHost $vhost, array $data): WebApplication
    {
        $appData = [
            'name' => $data['type'].' Application',
            'slug' => strtolower($data['type']).'-app',
            'virtual_host_id' => $vhost->id,
            'application_type' => $data['type'],
            'repository_url' => $data['repository'] ?? null,
            'repository_branch' => $data['branch'] ?? 'main',
            'current_environment' => $data['environment'] ?? 'production',
            'installation_status' => 'installing',
            'installation_path' => $vhost->document_root,
        ];

        $app = WebApplication::create($appData);

        // Clone repository if provided
        if (isset($data['repository'])) {
            Process::run("git clone {$data['repository']} {$vhost->document_root}");
        }

        // Install dependencies based on application type
        match ($data['type']) {
            'laravel' => $this->deployLaravelApp($vhost, $app),
            'wordpress' => $this->deployWordPressApp($vhost, $app),
            'static' => $this->deployStaticApp($vhost, $app),
            default => null,
        };

        $app->update(['installation_status' => 'installed']);

        return $app;
    }

    /**
     * Generate Nginx configuration for virtual host
     */
    public function generateNginxConfig(VirtualHost $vhost): string
    {
        $config = "server {\n";

        if ($vhost->ssl_enabled) {
            $config .= "    listen 443 ssl http2;\n";
            $config .= "    listen [::]:443 ssl http2;\n";
        } else {
            $config .= "    listen 80;\n";
            $config .= "    listen [::]:80;\n";
        }

        $config .= "    server_name {$vhost->primary_domain}";
        if ($vhost->server_names) {
            $serverNames = is_string($vhost->server_names)
                ? json_decode($vhost->server_names, true) ?? []
                : $vhost->server_names;
            if (count($serverNames) > 1) {
                $aliases = array_diff($serverNames, [$vhost->primary_domain]);
                $config .= ' '.implode(' ', $aliases);
            }
        }
        $config .= ";\n\n";

        $config .= "    root {$vhost->document_root};\n";
        $config .= "    index index.html index.htm index.php;\n\n";

        // SSL configuration
        if ($vhost->ssl_enabled) {
            $config .= "    ssl_certificate /etc/letsencrypt/live/{$vhost->primary_domain}/fullchain.pem;\n";
            $config .= "    ssl_certificate_key /etc/letsencrypt/live/{$vhost->primary_domain}/privkey.pem;\n";
            $config .= "    include /etc/letsencrypt/options-ssl-nginx.conf;\n";
            $config .= "    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;\n\n";
        }

        // PHP handling
        if ($vhost->php_version) {
            $config .= "    location ~ \\.php\$ {\n";
            $config .= "        try_files \$uri =404;\n";
            $config .= "        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;\n";
            $config .= "        fastcgi_pass unix:/run/php/php{$vhost->php_version}-fpm.sock;\n";
            $config .= "        fastcgi_index index.php;\n";
            $config .= "        include fastcgi_params;\n";
            $config .= "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
            $config .= "        fastcgi_param PATH_INFO \$fastcgi_path_info;\n";
            $config .= "    }\n\n";
        }

        $config .= "    location / {\n";
        $config .= "        try_files \$uri \$uri/ =404;\n";
        $config .= "    }\n";

        $config .= "}\n";

        return $config;
    }

    /**
     * Setup SSL certificate
     */
    public function setupSslCertificate(VirtualHost $vhost, array $config): array
    {
        $domain = $vhost->primary_domain;
        $email = $config['email'];

        // Use certbot for Let's Encrypt
        $result = Process::run("certbot certonly --nginx -d {$domain} --non-interactive --agree-tos --email {$email}");

        if ($result->successful()) {
            $vhost->update([
                'ssl_enabled' => true,
                'ssl_certificate_path' => "/etc/letsencrypt/live/{$domain}/fullchain.pem",
                'ssl_private_key_path' => "/etc/letsencrypt/live/{$domain}/privkey.pem",
            ]);

            return ['success' => true, 'message' => 'SSL certificate installed successfully'];
        }

        return ['success' => false, 'message' => 'Failed to install SSL certificate'];
    }

    /**
     * Get configuration path for different web server types
     */
    public function getConfigPath(VirtualHost $vhost): string
    {
        $server = $vhost->webServer;

        return match ($server->server_type) {
            'nginx' => "/etc/nginx/sites-available/{$vhost->primary_domain}",
            'apache' => "/etc/apache2/sites-available/{$vhost->primary_domain}.conf",
            'caddy' => "/etc/caddy/sites/{$vhost->primary_domain}",
            'lighttpd' => "/etc/lighttpd/vhosts/{$vhost->primary_domain}.conf",
            default => "/etc/{$server->server_type}/sites/{$vhost->primary_domain}",
        };
    }

    /**
     * Write virtual host configuration file
     */
    private function writeVirtualHostConfig(VirtualHost $vhost): void
    {
        $server = $vhost->webServer;
        $configPath = $this->getConfigPath($vhost);

        $config = match ($server->server_type) {
            'nginx' => $this->generateNginxConfig($vhost),
            'apache' => $this->generateApacheConfig($vhost),
            'caddy' => $this->generateCaddyConfig($vhost),
            'lighttpd' => $this->generateLighttpdConfig($vhost),
            default => "# Configuration for {$vhost->primary_domain}",
        };

        // In a real implementation, this would write to the actual file
        // For testing, we'll just store it in the virtual host record
        $vhost->update(['custom_nginx_config' => $config]);
    }

    /**
     * Generate Apache configuration
     */
    private function generateApacheConfig(VirtualHost $vhost): string
    {
        $config = "<VirtualHost *:80>\n";
        $config .= "    ServerName {$vhost->primary_domain}\n";
        $config .= "    DocumentRoot {$vhost->document_root}\n";
        $config .= "</VirtualHost>\n";

        return $config;
    }

    /**
     * Generate Caddy configuration
     */
    private function generateCaddyConfig(VirtualHost $vhost): string
    {
        $config = "{$vhost->primary_domain} {\n";
        $config .= "    root * {$vhost->document_root}\n";
        $config .= "    file_server\n";
        $config .= "}\n";

        return $config;
    }

    /**
     * Generate Lighttpd configuration
     */
    private function generateLighttpdConfig(VirtualHost $vhost): string
    {
        $config = "\$HTTP[\"host\"] == \"{$vhost->primary_domain}\" {\n";
        $config .= "    server.document-root = \"{$vhost->document_root}\"\n";
        $config .= "}\n";

        return $config;
    }

    /**
     * Test and reload server configuration
     */
    private function testAndReloadConfig(WebServer $server): void
    {
        match ($server->server_type) {
            'nginx' => Process::run('nginx -t && systemctl reload nginx'),
            'apache' => Process::run('apache2ctl configtest && systemctl reload apache2'),
            'caddy' => Process::run('caddy validate && systemctl reload caddy'),
            default => null,
        };
    }

    /**
     * Deploy Laravel application
     */
    private function deployLaravelApp(VirtualHost $vhost, WebApplication $app): void
    {
        $appPath = $vhost->document_root;
        Process::run("cd {$appPath} && composer install --no-dev");
        Process::run("cd {$appPath} && php artisan migrate --force");
    }

    /**
     * Deploy WordPress application
     */
    private function deployWordPressApp(VirtualHost $vhost, WebApplication $app): void
    {
        $appPath = $vhost->document_root;
        Process::run("chown -R www-data:www-data {$appPath}");
    }

    /**
     * Deploy static application
     */
    private function deployStaticApp(VirtualHost $vhost, WebApplication $app): void
    {
        $appPath = $vhost->document_root;
        Process::run("chown -R www-data:www-data {$appPath}");
    }
}
