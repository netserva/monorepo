<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Menu;
use NetServa\Cms\Models\Page;
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;

/**
 * NetServa CMS Default Content Seeder
 *
 * Seeds professional default content for NetServa installations.
 * Based on netserva.org branding - NOT client-specific content.
 *
 * This seeder should be run for new NetServa 3.0 installations
 * to provide a professional landing page and basic structure.
 */
class NetServaCmsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPages();
        $this->createNavigation();
        $this->createBlogContent();
    }

    /**
     * Create default pages
     */
    protected function createPages(): void
    {
        // Homepage
        Page::create([
            'title' => 'NetServa - Server Management Platform',
            'slug' => 'home',
            'excerpt' => 'Modern, efficient server management for professionals.',
            'content' => $this->getHomepageContent(),
            'template' => 'homepage',
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => 'NetServa - Server Management Platform',
            'meta_description' => 'NetServa 3.0 is a modern server management platform built on Laravel 12 and Filament 4. Manage your infrastructure with ease.',
            'meta_keywords' => 'server management, NetServa, infrastructure, DevOps, Laravel',
        ]);

        // About Page
        Page::create([
            'title' => 'About NetServa',
            'slug' => 'about',
            'excerpt' => 'Learn about the NetServa platform and our mission.',
            'content' => $this->getAboutContent(),
            'template' => 'default',
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => 'About NetServa - Server Management Platform',
            'meta_description' => 'NetServa provides modern server management tools for professionals who demand reliability and efficiency.',
            'meta_keywords' => 'about NetServa, server management, infrastructure platform',
        ]);

        // Features Page
        Page::create([
            'title' => 'Features',
            'slug' => 'features',
            'excerpt' => 'Discover what makes NetServa powerful.',
            'content' => $this->getFeaturesContent(),
            'template' => 'default',
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => 'NetServa Features - Modern Server Management',
            'meta_description' => 'Explore NetServa features including virtual host management, DNS automation, mail server configuration, and more.',
            'meta_keywords' => 'features, vhost management, DNS, mail server, automation',
        ]);
    }

    /**
     * Create navigation menus
     */
    protected function createNavigation(): void
    {
        Menu::create([
            'name' => 'Main Navigation',
            'location' => 'header',
            'is_active' => true,
            'items' => [
                ['label' => 'Home', 'url' => '/', 'order' => 1],
                ['label' => 'Features', 'url' => '/features', 'order' => 2],
                ['label' => 'About', 'url' => '/about', 'order' => 3],
                ['label' => 'Blog', 'url' => '/blog', 'order' => 4],
            ],
        ]);
    }

    /**
     * Create default blog content
     */
    protected function createBlogContent(): void
    {
        // Create categories
        $newsCategory = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'description' => 'Latest updates and announcements from NetServa',
            'type' => 'post',
        ]);

        $tutorialsCategory = Category::create([
            'name' => 'Tutorials',
            'slug' => 'tutorials',
            'description' => 'Step-by-step guides for using NetServa',
            'type' => 'post',
        ]);

        $devCategory = Category::create([
            'name' => 'Development',
            'slug' => 'development',
            'description' => 'Development updates and technical deep dives',
            'type' => 'post',
        ]);

        // Create tags
        $laravelTag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
        $filamentTag = Tag::create(['name' => 'Filament', 'slug' => 'filament']);
        $dnsTag = Tag::create(['name' => 'DNS', 'slug' => 'dns']);
        $mailTag = Tag::create(['name' => 'Mail', 'slug' => 'mail']);
        $sshTag = Tag::create(['name' => 'SSH', 'slug' => 'ssh']);
        $nginxTag = Tag::create(['name' => 'Nginx', 'slug' => 'nginx']);

        // Blog posts
        $posts = [
            [
                'title' => 'Welcome to NetServa 3.0',
                'slug' => 'welcome-to-netserva-3',
                'excerpt' => 'Introducing NetServa 3.0 - a complete rewrite built on modern Laravel 12 and Filament 4.',
                'content' => $this->getWelcomePostContent(),
                'categories' => [$newsCategory],
                'tags' => [$laravelTag, $filamentTag],
                'published_at' => now()->subDays(30),
            ],
            [
                'title' => 'Getting Started with Virtual Host Management',
                'slug' => 'getting-started-virtual-host-management',
                'excerpt' => 'Learn how to create and manage virtual hosts in NetServa 3.0.',
                'content' => $this->getVhostTutorialContent(),
                'categories' => [$tutorialsCategory],
                'tags' => [$nginxTag],
                'published_at' => now()->subDays(25),
            ],
            [
                'title' => 'Automated DNS Zone Management',
                'slug' => 'automated-dns-zone-management',
                'excerpt' => 'How NetServa automates PowerDNS configuration and zone file generation.',
                'content' => $this->getDnsTutorialContent(),
                'categories' => [$tutorialsCategory],
                'tags' => [$dnsTag],
                'published_at' => now()->subDays(20),
            ],
            [
                'title' => 'Mail Server Configuration Made Easy',
                'slug' => 'mail-server-configuration-made-easy',
                'excerpt' => 'Set up Postfix and Dovecot with NetServa\'s automated mail management.',
                'content' => $this->getMailTutorialContent(),
                'categories' => [$tutorialsCategory],
                'tags' => [$mailTag],
                'published_at' => now()->subDays(18),
            ],
            [
                'title' => 'SSH Fleet Management Best Practices',
                'slug' => 'ssh-fleet-management-best-practices',
                'excerpt' => 'Manage multiple servers efficiently with centralized SSH control.',
                'content' => $this->getSshBestPracticesContent(),
                'categories' => [$tutorialsCategory],
                'tags' => [$sshTag],
                'published_at' => now()->subDays(15),
            ],
            [
                'title' => 'Building with Laravel 12 and Filament 4',
                'slug' => 'building-with-laravel-12-filament-4',
                'excerpt' => 'Why we chose Laravel 12 and Filament 4 for NetServa 3.0.',
                'content' => $this->getLaravelFilamentContent(),
                'categories' => [$devCategory],
                'tags' => [$laravelTag, $filamentTag],
                'published_at' => now()->subDays(12),
            ],
            [
                'title' => 'Cross-Platform Service Management',
                'slug' => 'cross-platform-service-management',
                'excerpt' => 'How NetServa handles Alpine, Debian, and OpenWrt with a unified interface.',
                'content' => $this->getCrossPlatformContent(),
                'categories' => [$devCategory],
                'tags' => [],
                'published_at' => now()->subDays(10),
            ],
            [
                'title' => 'Database-First Configuration Architecture',
                'slug' => 'database-first-configuration-architecture',
                'excerpt' => 'Why NetServa stores all configuration in the database instead of files.',
                'content' => $this->getDatabaseFirstContent(),
                'categories' => [$devCategory],
                'tags' => [$laravelTag],
                'published_at' => now()->subDays(8),
            ],
            [
                'title' => 'Remote Execution Model Explained',
                'slug' => 'remote-execution-model-explained',
                'excerpt' => 'Understanding how NetServa executes commands from your workstation.',
                'content' => $this->getRemoteExecutionContent(),
                'categories' => [$devCategory],
                'tags' => [$sshTag],
                'published_at' => now()->subDays(6),
            ],
            [
                'title' => 'Nginx and PHP-FPM Automation',
                'slug' => 'nginx-php-fpm-automation',
                'excerpt' => 'Automated web server configuration for modern PHP applications.',
                'content' => $this->getNginxAutomationContent(),
                'categories' => [$tutorialsCategory],
                'tags' => [$nginxTag],
                'published_at' => now()->subDays(4),
            ],
            [
                'title' => 'Testing with Pest 4.0',
                'slug' => 'testing-with-pest-4',
                'excerpt' => 'How we achieve comprehensive test coverage with Pest 4.0.',
                'content' => $this->getPestTestingContent(),
                'categories' => [$devCategory],
                'tags' => [$laravelTag],
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'NetServa 3.0 Roadmap',
                'slug' => 'netserva-3-roadmap',
                'excerpt' => 'What\'s coming next for NetServa - Q1 2025 and beyond.',
                'content' => $this->getRoadmapContent(),
                'categories' => [$newsCategory],
                'tags' => [],
                'published_at' => now(),
            ],
        ];

        foreach ($posts as $postData) {
            $post = Post::create([
                'title' => $postData['title'],
                'slug' => $postData['slug'],
                'excerpt' => $postData['excerpt'],
                'content' => $postData['content'],
                'is_published' => true,
                'published_at' => $postData['published_at'],
                'meta_title' => $postData['title'],
                'meta_description' => $postData['excerpt'],
            ]);

            // Attach categories
            $post->categories()->attach($postData['categories']);

            // Attach tags
            if (! empty($postData['tags'])) {
                $post->tags()->attach($postData['tags']);
            }
        }
    }

    /**
     * Homepage content
     */
    protected function getHomepageContent(): string
    {
        return <<<'HTML'
<h2>Modern Server Management</h2>

<p>NetServa 3.0 is a comprehensive server management platform designed for professionals who demand reliability, efficiency, and modern tooling.</p>

<h3>Key Capabilities</h3>

<ul>
<li><strong>Virtual Host Management</strong> - Easily manage websites, domains, and hosting configurations</li>
<li><strong>DNS Automation</strong> - Integrated PowerDNS management with automatic zone generation</li>
<li><strong>Mail Server Control</strong> - Complete email infrastructure with Postfix and Dovecot</li>
<li><strong>SSH Fleet Management</strong> - Centralized control of multiple servers</li>
<li><strong>Web Server Configuration</strong> - Nginx and PHP-FPM automation</li>
</ul>

<h3>Built on Modern Technology</h3>

<p>NetServa 3.0 is built entirely on Laravel 12 and Filament 4, providing a beautiful, intuitive admin interface with powerful backend capabilities.</p>

<p><a href="/features">Explore Features</a> | <a href="/admin">Access Admin Panel</a></p>
HTML;
    }

    /**
     * About page content
     */
    protected function getAboutContent(): string
    {
        return <<<'HTML'
<h2>About NetServa</h2>

<p>NetServa is a modern server management platform designed to simplify infrastructure operations while maintaining flexibility and control.</p>

<h3>Our Mission</h3>

<p>We believe server management should be powerful yet approachable. NetServa provides professional-grade tools without unnecessary complexity.</p>

<h3>Technology Stack</h3>

<ul>
<li><strong>Framework:</strong> Laravel 12</li>
<li><strong>Admin Panel:</strong> Filament 4</li>
<li><strong>Testing:</strong> Pest 4.0</li>
<li><strong>Database:</strong> MySQL / SQLite</li>
<li><strong>Supported Platforms:</strong> Debian, Alpine, OpenWrt</li>
</ul>

<h3>Development Philosophy</h3>

<p>NetServa follows best practices including:</p>

<ul>
<li>Database-first configuration (no file-based configs)</li>
<li>Remote execution via SSH (no scripts copied to servers)</li>
<li>Comprehensive testing (100% coverage goal)</li>
<li>Clean, maintainable code</li>
</ul>

<p><a href="/">Return to Homepage</a></p>
HTML;
    }

    /**
     * Features page content
     */
    protected function getFeaturesContent(): string
    {
        return <<<'HTML'
<h2>NetServa Features</h2>

<h3>Virtual Host Management</h3>

<p>Create and manage virtual hosts with ease. NetServa handles all the configuration automatically:</p>

<ul>
<li>Automatic Nginx configuration generation</li>
<li>PHP-FPM pool creation and management</li>
<li>SSL certificate integration</li>
<li>Database and user provisioning</li>
</ul>

<h3>DNS Management</h3>

<p>Integrated PowerDNS management with:</p>

<ul>
<li>Automatic zone file generation</li>
<li>Support for native and master zone types</li>
<li>DNSSEC capabilities</li>
<li>Multi-server replication</li>
</ul>

<h3>Mail Server Automation</h3>

<p>Complete mail infrastructure control:</p>

<ul>
<li>Postfix configuration management</li>
<li>Dovecot mailbox provisioning</li>
<li>DKIM/SPF/DMARC setup</li>
<li>Spam filtering integration</li>
</ul>

<h3>SSH Fleet Management</h3>

<p>Centralized management of multiple servers:</p>

<ul>
<li>Execute commands across server fleet</li>
<li>SSH key management</li>
<li>Service control (cross-platform)</li>
<li>Log aggregation</li>
</ul>

<h3>Content Management</h3>

<p>This CMS you're viewing right now is built into NetServa!</p>

<ul>
<li>Pages and blog posts</li>
<li>SEO optimization tools</li>
<li>Media library</li>
<li>Categories and tags</li>
</ul>

<p><a href="/">Return to Homepage</a> | <a href="/admin">Access Admin Panel</a></p>
HTML;
    }

    /**
     * Welcome blog post content
     */
    protected function getWelcomePostContent(): string
    {
        return <<<'HTML'
<p>We're excited to announce the release of NetServa 3.0 - a complete rewrite of our server management platform built on modern technologies.</p>

<h3>What's New in 3.0</h3>

<p>NetServa 3.0 represents a complete architectural redesign:</p>

<ul>
<li><strong>Laravel 12</strong> - Built on the latest Laravel LTS framework</li>
<li><strong>Filament 4</strong> - Beautiful, intuitive admin interface</li>
<li><strong>Modular Architecture</strong> - Plugin-based system for extensibility</li>
<li><strong>Improved Testing</strong> - Comprehensive Pest 4.0 test suite</li>
<li><strong>Better Performance</strong> - Optimized database queries and caching</li>
</ul>

<h3>Architectural Improvements</h3>

<p>We've made significant improvements to how NetServa operates:</p>

<ul>
<li>All configuration stored in database (no more config file management)</li>
<li>Remote execution model (scripts execute from workstation, not copied to servers)</li>
<li>Cross-platform service management (Alpine, Debian, OpenWrt)</li>
<li>Integrated CMS for public-facing pages</li>
</ul>

<h3>Getting Started</h3>

<p>Ready to explore? Check out the <a href="/features">features page</a> or dive right into the <a href="/admin">admin panel</a>.</p>

<p>For documentation and support, visit the project repository or join our community.</p>

<h3>What's Next</h3>

<p>We'll be sharing regular updates on new features, improvements, and tips for getting the most out of NetServa 3.0.</p>

<p>Stay tuned!</p>
HTML;
    }

    protected function getVhostTutorialContent(): string
    {
        return <<<'HTML'
<p>Virtual host management is one of NetServa's core features. This tutorial will walk you through creating and configuring your first virtual host.</p>

<h3>What is a Virtual Host?</h3>
<p>A virtual host represents a website or application hosted on your server. NetServa automates all the configuration needed to get your site running.</p>

<h3>Creating a Virtual Host</h3>
<p>From the admin panel, navigate to Web → Virtual Hosts and click "Create Virtual Host". You'll need to provide:</p>
<ul>
<li>Domain name (e.g., example.com)</li>
<li>Server location (which vnode to deploy to)</li>
<li>PHP version (if applicable)</li>
<li>Database requirements</li>
</ul>

<h3>What NetServa Does Automatically</h3>
<p>When you create a vhost, NetServa handles:</p>
<ul>
<li>Nginx configuration generation and deployment</li>
<li>PHP-FPM pool creation</li>
<li>Directory structure setup</li>
<li>Database and user provisioning</li>
<li>DNS record creation</li>
</ul>

<p>No manual SSH required - everything is managed from the admin panel!</p>
HTML;
    }

    protected function getDnsTutorialContent(): string
    {
        return <<<'HTML'
<p>NetServa integrates with PowerDNS to provide automated DNS management. This guide covers DNS configuration and zone management.</p>

<h3>DNS Architecture</h3>
<p>NetServa uses PowerDNS with MySQL backend for dynamic zone management. All DNS records are stored in the database and can be managed through the admin panel.</p>

<h3>Automatic Zone Creation</h3>
<p>When you create a virtual host, NetServa automatically:</p>
<ul>
<li>Creates a DNS zone for the domain</li>
<li>Adds A records pointing to the correct server</li>
<li>Configures MX records if mail is enabled</li>
<li>Sets up SPF, DKIM, and DMARC records</li>
</ul>

<h3>Manual DNS Management</h3>
<p>You can also manage DNS records manually through the DNS module:</p>
<ul>
<li>Add, edit, or delete individual records</li>
<li>Import zone files</li>
<li>Export zones for backup</li>
<li>Configure DNSSEC</li>
</ul>

<p>DNS changes propagate immediately to your nameservers.</p>
HTML;
    }

    protected function getMailTutorialContent(): string
    {
        return <<<'HTML'
<p>Setting up a mail server traditionally requires extensive configuration. NetServa simplifies this with automated Postfix and Dovecot management.</p>

<h3>Mail Server Components</h3>
<p>NetServa configures:</p>
<ul>
<li>Postfix - SMTP server for sending/receiving mail</li>
<li>Dovecot - IMAP/POP3 server for mailbox access</li>
<li>DKIM signing for email authentication</li>
<li>SPF and DMARC records</li>
</ul>

<h3>Creating Mailboxes</h3>
<p>From the Mail module in the admin panel:</p>
<ol>
<li>Navigate to Mail → Mailboxes</li>
<li>Click "Create Mailbox"</li>
<li>Enter email address and password</li>
<li>Set quota limits</li>
</ol>

<p>NetServa handles all backend configuration including virtual mailbox maps, database updates, and service reloads.</p>

<h3>Email Authentication</h3>
<p>NetServa automatically configures DKIM signing and creates the necessary DNS records for SPF and DMARC, ensuring your emails have the best chance of reaching the inbox.</p>
HTML;
    }

    protected function getSshBestPracticesContent(): string
    {
        return <<<'HTML'
<p>Managing multiple servers requires efficient SSH workflows. NetServa's fleet management provides centralized control while following security best practices.</p>

<h3>Centralized SSH Key Management</h3>
<p>NetServa stores SSH keys securely and automatically distributes them to your servers. Benefits include:</p>
<ul>
<li>Single source of truth for authorized keys</li>
<li>Easy key rotation</li>
<li>Per-server access control</li>
<li>Audit trail of SSH access</li>
</ul>

<h3>Remote Command Execution</h3>
<p>Execute commands across your fleet from the admin panel. NetServa's execution model:</p>
<ul>
<li>Runs from your workstation</li>
<li>Never copies scripts to remote servers</li>
<li>Provides real-time output</li>
<li>Logs all executions</li>
</ul>

<h3>Security Considerations</h3>
<p>Best practices NetServa enforces:</p>
<ul>
<li>No root SSH access (uses sudo)</li>
<li>Key-based authentication only</li>
<li>Fail2ban integration</li>
<li>Connection rate limiting</li>
</ul>
HTML;
    }

    protected function getLaravelFilamentContent(): string
    {
        return <<<'HTML'
<p>Building NetServa 3.0 on Laravel 12 and Filament 4 was a strategic decision that delivers significant benefits.</p>

<h3>Why Laravel 12?</h3>
<p>Laravel 12 provides:</p>
<ul>
<li>Modern PHP 8.4 features</li>
<li>Improved performance and type safety</li>
<li>Excellent ecosystem and community</li>
<li>Long-term support</li>
</ul>

<h3>Why Filament 4?</h3>
<p>Filament 4 delivers:</p>
<ul>
<li>Beautiful, professional admin interface</li>
<li>Rapid development of CRUD interfaces</li>
<li>Built-in form builder and table components</li>
<li>Native dark mode support</li>
<li>Excellent mobile responsiveness</li>
</ul>

<h3>Development Experience</h3>
<p>The combination of Laravel and Filament allows us to:</p>
<ul>
<li>Build features faster</li>
<li>Maintain consistent UI/UX</li>
<li>Write less boilerplate code</li>
<li>Focus on business logic</li>
</ul>

<p>This technology stack enables us to deliver professional-grade server management tools efficiently.</p>
HTML;
    }

    protected function getCrossPlatformContent(): string
    {
        return <<<'HTML'
<p>One of NetServa's most powerful features is cross-platform service management. A single interface controls Alpine, Debian, and OpenWrt servers.</p>

<h3>The Challenge</h3>
<p>Different Linux distributions use different service managers:</p>
<ul>
<li>Alpine - OpenRC</li>
<li>Debian - systemd</li>
<li>OpenWrt - procd</li>
</ul>

<h3>The NetServa Solution</h3>
<p>We created the <code>sc()</code> shell function that abstracts service control:</p>
<pre>sc status nginx    # Works on all platforms
sc reload postfix  # Unified interface
sc restart dovecot # Same command everywhere</pre>

<h3>OS Detection</h3>
<p>NetServa automatically detects the operating system and uses the appropriate commands:</p>
<ul>
<li>Checks for <code>/etc/os-release</code></li>
<li>Identifies service manager</li>
<li>Translates commands automatically</li>
</ul>

<p>This abstraction means you write platform-agnostic scripts that work everywhere.</p>
HTML;
    }

    protected function getDatabaseFirstContent(): string
    {
        return <<<'HTML'
<p>NetServa 3.0 uses a database-first architecture - all configuration lives in the database, not in files.</p>

<h3>Why Database-First?</h3>
<p>Traditional server management tools use file-based configuration. This creates problems:</p>
<ul>
<li>Hard to query and report on</li>
<li>Difficult to version and track changes</li>
<li>No relational integrity</li>
<li>Synchronization challenges</li>
</ul>

<h3>The Database Approach</h3>
<p>By storing all configuration in the database:</p>
<ul>
<li>Easy to query and filter</li>
<li>Built-in change tracking</li>
<li>Relational integrity via foreign keys</li>
<li>Simple backup and restore</li>
<li>API-friendly</li>
</ul>

<h3>How It Works</h3>
<p>When you configure a virtual host:</p>
<ol>
<li>Data is stored in the <code>vhosts</code> table</li>
<li>Credentials go in the <code>vconfs</code> table</li>
<li>Configuration files are generated on-the-fly</li>
<li>Files are deployed to remote servers as needed</li>
</ol>

<p>The database is the single source of truth.</p>
HTML;
    }

    protected function getRemoteExecutionContent(): string
    {
        return <<<'HTML'
<p>NetServa's remote execution model is fundamentally different from traditional configuration management tools.</p>

<h3>Traditional Approach</h3>
<p>Most tools copy scripts to remote servers and execute them there. Problems with this:</p>
<ul>
<li>Scripts scattered across servers</li>
<li>Version control challenges</li>
<li>Security concerns (scripts persist on servers)</li>
<li>Cleanup required</li>
</ul>

<h3>NetServa's Approach</h3>
<p>Scripts execute from your workstation, sending commands via SSH:</p>
<ul>
<li>All scripts in one location (version controlled)</li>
<li>Nothing persists on remote servers</li>
<li>Immediate updates (no deployment needed)</li>
<li>Better security (no script files to secure)</li>
</ul>

<h3>Implementation</h3>
<p>NetServa uses <code>RemoteExecutionService</code> which:</p>
<ol>
<li>Builds script content from database config</li>
<li>Sends via SSH heredoc</li>
<li>Executes in bash shell</li>
<li>Returns output to admin panel</li>
</ol>

<p>This model keeps your infrastructure clean while maintaining full control.</p>
HTML;
    }

    protected function getNginxAutomationContent(): string
    {
        return <<<'HTML'
<p>NetServa automates Nginx and PHP-FPM configuration, eliminating manual configuration file editing.</p>

<h3>Nginx Configuration Generation</h3>
<p>For each virtual host, NetServa generates:</p>
<ul>
<li>Server blocks with correct document roots</li>
<li>SSL/TLS configuration</li>
<li>Security headers</li>
<li>Caching rules</li>
<li>Gzip compression</li>
</ul>

<h3>PHP-FPM Pool Management</h3>
<p>Each site gets its own PHP-FPM pool:</p>
<ul>
<li>Isolated processes</li>
<li>Per-site resource limits</li>
<li>Custom PHP versions</li>
<li>Performance tuning</li>
</ul>

<h3>Template System</h3>
<p>NetServa uses a template system for configuration:</p>
<pre>{{ vhost.domain }}
{{ vhost.webroot }}
{{ vhost.php_version }}</pre>

<p>Templates ensure consistency and reduce configuration errors.</p>

<h3>Zero Downtime Deployments</h3>
<p>When updating configuration:</p>
<ol>
<li>New config is tested (<code>nginx -t</code>)</li>
<li>Only applied if valid</li>
<li>Graceful reload (no dropped connections)</li>
</ol>
HTML;
    }

    protected function getPestTestingContent(): string
    {
        return <<<'HTML'
<p>NetServa 3.0 uses Pest 4.0 for testing, aiming for comprehensive coverage of all functionality.</p>

<h3>Why Pest?</h3>
<p>Pest provides:</p>
<ul>
<li>Beautiful, readable test syntax</li>
<li>Fast execution</li>
<li>Excellent Laravel integration</li>
<li>Type coverage analysis</li>
<li>Parallel test execution</li>
</ul>

<h3>Testing Strategy</h3>
<p>We write tests for:</p>
<ul>
<li>Unit tests for models and services</li>
<li>Feature tests for HTTP endpoints</li>
<li>Browser tests for UI workflows</li>
<li>Integration tests for remote execution</li>
</ul>

<h3>Example Test</h3>
<pre>it('creates a virtual host', function () {
    $vhost = Vhost::factory()->create();

    expect($vhost)
        ->domain->toBe('example.com')
        ->is_active->toBeTrue();
});</pre>

<h3>Coverage Goals</h3>
<p>We aim for:</p>
<ul>
<li>100% coverage of critical paths</li>
<li>All API endpoints tested</li>
<li>All Filament resources tested</li>
<li>All remote execution scripts tested</li>
</ul>
HTML;
    }

    protected function getRoadmapContent(): string
    {
        return <<<'HTML'
<p>Here's what's coming in NetServa 3.0 over the next few months.</p>

<h3>Q1 2025</h3>
<ul>
<li><strong>Backup Module</strong> - Automated backup configuration for databases, files, and configurations</li>
<li><strong>Monitoring Integration</strong> - Built-in server monitoring and alerting</li>
<li><strong>API Documentation</strong> - Complete API docs for automation</li>
</ul>

<h3>Q2 2025</h3>
<ul>
<li><strong>Container Support</strong> - Docker and LXC/Incus management</li>
<li><strong>Load Balancer Config</strong> - HAProxy and Nginx load balancer automation</li>
<li><strong>SSL Certificate Automation</strong> - Let's Encrypt integration</li>
</ul>

<h3>Q3 2025</h3>
<ul>
<li><strong>Multi-tenancy</strong> - Support for multiple organizations</li>
<li><strong>Role-Based Access Control</strong> - Granular permissions</li>
<li><strong>Audit Logging</strong> - Complete audit trail</li>
</ul>

<h3>Future Possibilities</h3>
<ul>
<li>Kubernetes integration</li>
<li>Cloud provider APIs (AWS, DigitalOcean, etc.)</li>
<li>Terraform/OpenTofu integration</li>
<li>Mobile app for monitoring</li>
</ul>

<p>Have suggestions? We'd love to hear them! Visit our GitHub repository to submit feature requests.</p>
HTML;
    }
}
