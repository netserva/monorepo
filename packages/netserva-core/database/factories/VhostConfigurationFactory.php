<?php

namespace NetServa\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Core\Models\VhostConfiguration;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\NetServa\Core\Models\VhostConfiguration>
 */
class VhostConfigurationFactory extends Factory
{
    protected $model = VhostConfiguration::class;

    public function definition(): array
    {
        $vnode = $this->faker->randomElement(['ns1', 'ns2', 'motd', 'mgo', 'bion']);
        $vhost = $this->faker->domainName();

        return [
            'vnode' => $vnode,
            'vhost' => $vhost,
            'filepath' => "/home/markc/.ns/var/{$vnode}/{$vhost}",
            'variables' => $this->generateStandardVariables($vnode, $vhost),
            'migrated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'file_modified_at' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
            'checksum' => $this->faker->md5(),
        ];
    }

    private function generateStandardVariables(string $vnode, string $vhost): array
    {
        $domain = explode('.', $vhost);
        $domainName = str_replace('.', '_', $vhost);

        return [
            'ADMIN' => 'sysadm',
            'AHOST' => $vhost,
            'AMAIL' => "admin@{$vhost}",
            'ANAME' => 'System Administrator',
            'APASS' => $this->faker->password(12, 20),
            'A_GID' => '1001',
            'A_UID' => '1001',
            'BPATH' => '/home/backups',
            'CIMAP' => '/etc/dovecot',
            'CSMTP' => '/etc/postfix',
            'C_DNS' => '/etc/powerdns',
            'C_FPM' => '/etc/php/8.4/fpm',
            'C_SQL' => '/etc/mysql',
            'C_WEB' => '/etc/nginx',
            'DBMYS' => '/var/lib/mysql',
            'DBSQL' => '/var/lib/sqlite',
            'DHOST' => 'localhost',
            'DNAME' => $vnode === $vhost ? 'sysadm' : $domainName,
            'DPASS' => $this->faker->password(12, 20),
            'DPATH' => '/var/lib/sqlite/sysadm',
            'DPORT' => '3306',
            'DTYPE' => $this->faker->randomElement(['sqlite', 'mysql']),
            'DUSER' => 'sysadm',
            'EPASS' => $this->faker->password(12, 20),
            'EXMYS' => "mysql -usysadm -p{$this->faker->password(12, 20)} sysadm",
            'EXSQL' => 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db',
            'HDOMN' => $vhost,
            'HNAME' => $vnode,
            'IP4_0' => $this->faker->ipv4(),
            'MHOST' => $vhost,
            'MPATH' => "/srv/{$vhost}/msg",
            'OSMIR' => 'http://dl-cdn.alpinelinux.org',
            'OSREL' => $this->faker->randomElement(['edge', '3.19', '3.18']),
            'OSTYP' => $this->faker->randomElement(['alpine', 'debian', 'ubuntu']),
            'C_SSL' => '/etc/ssl',
            'SQCMD' => 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db',
            'SQDNS' => 'sqlite3 /var/lib/sqlite/sysadm/powerdns.db',
            'TAREA' => $this->faker->randomElement(['Australia', 'America', 'Europe', 'Asia']),
            'TCITY' => $this->faker->city(),
            'UPASS' => $this->faker->password(12, 20),
            'UPATH' => "/srv/{$vhost}",
            'UUSER' => 'u1001',
            'U_GID' => '1001',
            'U_SHL' => '/bin/bash',
            'U_UID' => '1001',
            'VHOST' => $vhost,
            'VNODE' => $vnode,
            'VPATH' => '/srv',
            'VUSER' => 'admin',
            'V_PHP' => $this->faker->randomElement(['8.4', '8.3', '8.2']),
            'WPASS' => $this->faker->password(12, 20),
            'WPATH' => "/srv/{$vhost}/web",
            'WPUSR' => $this->faker->userName(),
            'WUGID' => $this->faker->randomElement(['nginx', 'www-data']),
        ];
    }

    /**
     * Create a configuration with minimal required variables
     */
    public function minimal(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'variables' => [
                    'VHOST' => $attributes['vhost'],
                    'VNODE' => $attributes['vnode'],
                    'ADMIN' => 'sysadm',
                    'VPATH' => '/srv',
                ],
            ];
        });
    }

    /**
     * Create a configuration for a specific vnode
     */
    public function forVnode(string $vnode): static
    {
        return $this->state([
            'vnode' => $vnode,
        ]);
    }

    /**
     * Create a configuration for a specific vhost
     */
    public function forVhost(string $vhost): static
    {
        return $this->state([
            'vhost' => $vhost,
        ]);
    }

    /**
     * Create a configuration that was recently migrated
     */
    public function recentlyMigrated(): static
    {
        return $this->state([
            'migrated_at' => now()->subMinutes($this->faker->numberBetween(1, 60)),
        ]);
    }

    /**
     * Create a configuration with sqlite database type
     */
    public function sqlite(): static
    {
        return $this->state(function (array $attributes) {
            $variables = $attributes['variables'] ?? [];
            $variables['DTYPE'] = 'sqlite';
            $variables['DPORT'] = '0';
            $variables['EXSQL'] = "sqlite3 {$variables['DPATH']}/{$variables['DNAME']}.db";

            return ['variables' => $variables];
        });
    }

    /**
     * Create a configuration with mysql database type
     */
    public function mysql(): static
    {
        return $this->state(function (array $attributes) {
            $variables = $attributes['variables'] ?? [];
            $variables['DTYPE'] = 'mysql';
            $variables['DPORT'] = '3306';
            $variables['EXMYS'] = "mysql -u{$variables['DUSER']} -p{$variables['DPASS']} {$variables['DNAME']}";

            return ['variables' => $variables];
        });
    }
}
