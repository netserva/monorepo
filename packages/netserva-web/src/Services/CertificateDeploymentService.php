<?php

namespace NetServa\Web\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Web\Models\SslCertificate;
use NetServa\Web\Models\SslCertificateDeployment;

class CertificateDeploymentService
{
    /**
     * Deploy certificate to server
     */
    public function deployCertificate(SslCertificate $certificate, array $deploymentConfig): SslCertificateDeployment
    {
        $deployment = SslCertificateDeployment::create([
            'ssl_certificate_id' => $certificate->id,
            'server_name' => $deploymentConfig['server_name'],
            'deployment_type' => $deploymentConfig['deployment_type'] ?? 'nginx',
            'config_path' => $deploymentConfig['config_path'] ?? '/etc/ssl/certs',
            'status' => 'pending',
            'deployed_at' => null,
        ]);

        try {
            switch ($deployment->deployment_type) {
                case 'nginx':
                    $this->deployToNginx($certificate, $deployment, $deploymentConfig);
                    break;
                case 'apache':
                    $this->deployToApache($certificate, $deployment, $deploymentConfig);
                    break;
                case 'file':
                    $this->deployToFileSystem($certificate, $deployment, $deploymentConfig);
                    break;
                default:
                    throw new Exception("Unsupported deployment type: {$deployment->deployment_type}");
            }

            $deployment->update([
                'status' => 'deployed',
                'deployed_at' => now(),
            ]);

            Log::info('Certificate deployed successfully', [
                'certificate_id' => $certificate->id,
                'deployment_id' => $deployment->id,
                'server_name' => $deployment->server_name,
            ]);

        } catch (Exception $e) {
            $deployment->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Certificate deployment failed', [
                'certificate_id' => $certificate->id,
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $deployment;
    }

    /**
     * Deploy certificate to Nginx
     */
    private function deployToNginx(SslCertificate $certificate, SslCertificateDeployment $deployment, array $config): void
    {
        $certPath = $config['config_path'].'/'.$certificate->domain.'.crt';
        $keyPath = $config['config_path'].'/'.$certificate->domain.'.key';

        // Write certificate files
        File::put($certPath, $certificate->certificate_content);
        File::put($keyPath, $certificate->private_key_content);

        // Set proper permissions
        chmod($certPath, 0644);
        chmod($keyPath, 0600);

        // Test nginx configuration
        $result = Process::run('nginx -t');

        if ($result->failed()) {
            throw new Exception('Nginx configuration test failed: '.$result->errorOutput());
        }

        // Reload nginx
        $result = Process::run('systemctl reload nginx');

        if ($result->failed()) {
            throw new Exception('Nginx reload failed: '.$result->errorOutput());
        }

        $deployment->update([
            'certificate_path' => $certPath,
            'private_key_path' => $keyPath,
        ]);
    }

    /**
     * Deploy certificate to Apache
     */
    private function deployToApache(SslCertificate $certificate, SslCertificateDeployment $deployment, array $config): void
    {
        $certPath = $config['config_path'].'/'.$certificate->domain.'.crt';
        $keyPath = $config['config_path'].'/'.$certificate->domain.'.key';

        // Write certificate files
        File::put($certPath, $certificate->certificate_content);
        File::put($keyPath, $certificate->private_key_content);

        // Set proper permissions
        chmod($certPath, 0644);
        chmod($keyPath, 0600);

        // Test apache configuration
        $result = Process::run('apache2ctl configtest');

        if ($result->failed()) {
            throw new Exception('Apache configuration test failed: '.$result->errorOutput());
        }

        // Reload apache
        $result = Process::run('systemctl reload apache2');

        if ($result->failed()) {
            throw new Exception('Apache reload failed: '.$result->errorOutput());
        }

        $deployment->update([
            'certificate_path' => $certPath,
            'private_key_path' => $keyPath,
        ]);
    }

    /**
     * Deploy certificate to file system only
     */
    private function deployToFileSystem(SslCertificate $certificate, SslCertificateDeployment $deployment, array $config): void
    {
        $certPath = $config['config_path'].'/'.$certificate->domain.'.crt';
        $keyPath = $config['config_path'].'/'.$certificate->domain.'.key';

        // Ensure directory exists
        $directory = dirname($certPath);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Write certificate files
        File::put($certPath, $certificate->certificate_content);
        File::put($keyPath, $certificate->private_key_content);

        // Set proper permissions
        chmod($certPath, 0644);
        chmod($keyPath, 0600);

        $deployment->update([
            'certificate_path' => $certPath,
            'private_key_path' => $keyPath,
        ]);
    }

    /**
     * Remove deployed certificate
     */
    public function removeCertificate(SslCertificateDeployment $deployment): bool
    {
        try {
            // Remove certificate files
            if ($deployment->certificate_path && File::exists($deployment->certificate_path)) {
                File::delete($deployment->certificate_path);
            }

            if ($deployment->private_key_path && File::exists($deployment->private_key_path)) {
                File::delete($deployment->private_key_path);
            }

            // Reload web server if needed
            switch ($deployment->deployment_type) {
                case 'nginx':
                    Process::run('systemctl reload nginx');
                    break;
                case 'apache':
                    Process::run('systemctl reload apache2');
                    break;
            }

            $deployment->update([
                'status' => 'removed',
                'removed_at' => now(),
            ]);

            Log::info('Certificate deployment removed', [
                'deployment_id' => $deployment->id,
                'server_name' => $deployment->server_name,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to remove certificate deployment', [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test certificate deployment
     */
    public function testDeployment(SslCertificateDeployment $deployment): array
    {
        $results = [
            'certificate_accessible' => false,
            'private_key_accessible' => false,
            'web_server_config_valid' => false,
            'ssl_handshake_valid' => false,
        ];

        try {
            // Check certificate file accessibility
            if ($deployment->certificate_path && File::exists($deployment->certificate_path)) {
                $results['certificate_accessible'] = true;
            }

            // Check private key file accessibility
            if ($deployment->private_key_path && File::exists($deployment->private_key_path)) {
                $results['private_key_accessible'] = true;
            }

            // Test web server configuration
            switch ($deployment->deployment_type) {
                case 'nginx':
                    $result = Process::run('nginx -t');
                    $results['web_server_config_valid'] = $result->successful();
                    break;
                case 'apache':
                    $result = Process::run('apache2ctl configtest');
                    $results['web_server_config_valid'] = $result->successful();
                    break;
                default:
                    $results['web_server_config_valid'] = true; // File-only deployment
            }

            // Test SSL handshake (simplified)
            if ($deployment->server_name) {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $result = @file_get_contents("https://{$deployment->server_name}", false, $context);
                $results['ssl_handshake_valid'] = $result !== false;
            }

        } catch (Exception $e) {
            Log::error('Certificate deployment test failed', [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }
}
