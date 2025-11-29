<?php

namespace NetServa\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\PlatformProfile;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Core\Models\PlatformProfile>
 */
class PlatformProfileFactory extends Factory
{
    protected $model = PlatformProfile::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['provider', 'server', 'host', 'vhost']);
        $name = $this->faker->slug(2);

        return [
            'profile_type' => $type,
            'profile_name' => $name,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(2),
            'filepath' => "/home/markc/.ns/etc/{$type}s/{$name}.md",
            'content' => $this->generateMarkdownContent($type, $name),
            'metadata' => $this->generateMetadata($type),
            'tags' => $this->generateTags($type),
            'category' => $this->generateCategory($type),
            'status' => $this->faker->randomElement(['active', 'deprecated', 'archived']),
            'migrated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'file_modified_at' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
            'checksum' => $this->faker->md5(),
        ];
    }

    private function generateMarkdownContent(string $type, string $name): string
    {
        $title = ucfirst($type).' Profile: '.ucwords(str_replace('-', ' ', $name));

        $content = "# {$title}\n\n";
        $content .= "## Overview\n";
        $content .= '- **Type**: '.ucfirst($type)."\n";
        $content .= "- **Name**: {$name}\n";
        $content .= "- **Status**: Active\n";
        $content .= '- **Purpose**: '.$this->faker->sentence()."\n\n";

        if ($type === 'provider') {
            $content .= "## Provider Details\n";
            $content .= '- **Provider Type**: '.$this->faker->randomElement(['Cloud VPS', 'Dedicated Hosting', 'Shared Hosting'])."\n";
            $content .= '- **Location**: '.$this->faker->country()."\n";
            $content .= '- **Website**: https://'.$this->faker->domainName()."\n\n";

            $content .= "## Services\n";
            $content .= "- Virtual Private Servers\n";
            $content .= "- Load Balancers\n";
            $content .= "- DNS Management\n\n";
        } elseif ($type === 'server') {
            $content .= "## Server Specifications\n";
            $content .= '- **CPU**: '.$this->faker->randomElement(['2 cores', '4 cores', '8 cores'])."\n";
            $content .= '- **Memory**: '.$this->faker->randomElement(['2GB', '4GB', '8GB', '16GB'])."\n";
            $content .= '- **Storage**: '.$this->faker->randomElement(['50GB SSD', '100GB SSD', '200GB SSD'])."\n";
            $content .= '- **OS**: '.$this->faker->randomElement(['Ubuntu 22.04', 'Alpine Linux', 'Debian 12'])."\n\n";

            $content .= "## Services\n";
            $content .= "- Nginx Web Server\n";
            $content .= "- PHP-FPM\n";
            $content .= "- MySQL/MariaDB\n\n";
        }

        $content .= "## Configuration\n";
        $content .= $this->faker->paragraph(3)."\n\n";

        $content .= "## Maintenance\n";
        $content .= $this->faker->paragraph(2)."\n\n";

        return $content;
    }

    private function generateMetadata(string $type): array
    {
        $metadata = [
            'type' => $type,
            'created_at' => $this->faker->dateTimeThisYear()->format('Y-m-d'),
            'updated_at' => $this->faker->dateTimeThisMonth()->format('Y-m-d'),
        ];

        if ($type === 'provider') {
            $metadata['provider_type'] = $this->faker->randomElement(['Cloud', 'VPS', 'Dedicated']);
            $metadata['location'] = $this->faker->country();
            $metadata['website'] = 'https://'.$this->faker->domainName();
        } elseif ($type === 'server') {
            $metadata['cpu_cores'] = $this->faker->randomElement(['2', '4', '8']);
            $metadata['memory_gb'] = $this->faker->randomElement(['2', '4', '8', '16']);
            $metadata['storage_gb'] = $this->faker->randomElement(['50', '100', '200']);
            $metadata['os'] = $this->faker->randomElement(['ubuntu', 'alpine', 'debian']);
        }

        return $metadata;
    }

    private function generateTags(string $type): array
    {
        $baseTags = [$type, 'netserva', 'infrastructure'];

        $typeTags = [
            'provider' => ['cloud', 'hosting', 'vps'],
            'server' => ['production', 'linux', 'web'],
            'host' => ['virtual', 'container', 'lxc'],
            'vhost' => ['domain', 'website', 'ssl'],
        ];

        $tags = array_merge($baseTags, $this->faker->randomElements($typeTags[$type] ?? [], 2));

        return array_unique($tags);
    }

    private function generateCategory(string $type): string
    {
        $categories = [
            'provider' => ['cloud-provider', 'hosting-provider', 'vps-provider'],
            'server' => ['production-server', 'development-server', 'container-server'],
            'host' => ['physical-host', 'virtual-host', 'container-host'],
            'vhost' => ['web-vhost', 'mail-vhost', 'api-vhost'],
        ];

        return $this->faker->randomElement($categories[$type] ?? [$type]);
    }

    /**
     * Create a profile of a specific type
     */
    public function ofType(string $type): static
    {
        return $this->state([
            'profile_type' => $type,
        ]);
    }

    /**
     * Create a provider profile
     */
    public function provider(): static
    {
        return $this->ofType('provider');
    }

    /**
     * Create a server profile
     */
    public function server(): static
    {
        return $this->ofType('server');
    }

    /**
     * Create a host profile
     */
    public function host(): static
    {
        return $this->ofType('host');
    }

    /**
     * Create a vhost profile
     */
    public function vhost(): static
    {
        return $this->ofType('vhost');
    }

    /**
     * Create an active profile
     */
    public function active(): static
    {
        return $this->state([
            'status' => 'active',
        ]);
    }

    /**
     * Create a deprecated profile
     */
    public function deprecated(): static
    {
        return $this->state([
            'status' => 'deprecated',
        ]);
    }

    /**
     * Create a recently migrated profile
     */
    public function recentlyMigrated(): static
    {
        return $this->state([
            'migrated_at' => now()->subMinutes($this->faker->numberBetween(1, 60)),
        ]);
    }

    /**
     * Create a profile with specific category
     */
    public function withCategory(string $category): static
    {
        return $this->state([
            'category' => $category,
        ]);
    }
}
