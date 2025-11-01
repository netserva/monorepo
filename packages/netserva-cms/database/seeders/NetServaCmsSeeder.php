<?php

declare(strict_types=1);

namespace NetServa\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Page;
use NetServa\Cms\Models\Post;

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
     * Create default blog content
     */
    protected function createBlogContent(): void
    {
        // Create News category
        $newsCategory = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'description' => 'Latest updates and announcements from NetServa',
            'type' => 'post',
            'is_active' => true,
        ]);

        // Welcome blog post
        $welcomePost = Post::create([
            'title' => 'Welcome to NetServa 3.0',
            'slug' => 'welcome-to-netserva-3',
            'excerpt' => 'Introducing NetServa 3.0 - a complete rewrite built on modern Laravel 12 and Filament 4.',
            'content' => $this->getWelcomePostContent(),
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => 'Welcome to NetServa 3.0',
            'meta_description' => 'NetServa 3.0 brings modern server management with Laravel 12, Filament 4, and a beautiful new interface.',
            'meta_keywords' => 'NetServa 3.0, Laravel 12, Filament 4, server management, announcement',
        ]);

        // Attach category via pivot table
        $welcomePost->categories()->attach($newsCategory);
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
}
