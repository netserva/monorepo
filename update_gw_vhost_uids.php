<?php
/**
 * Update vconfs table with correct uid/gid from /etc/passwd on gw
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use NetServa\Fleet\Models\FleetVHost;
use NetServa\Cli\Models\VConf;

// Mapping from /etc/passwd on gw server
$uidGidMap = [
    'mail.goldcoast.org' => ['uid' => 1000, 'gid' => 1000, 'uuser' => 'sysadm'],
    'goldcoast.org'      => ['uid' => 1001, 'gid' => 1001, 'uuser' => 'u1001'],
    'motd.com'           => ['uid' => 1002, 'gid' => 1002, 'uuser' => 'u1002'],
    'netserva.org'       => ['uid' => 1003, 'gid' => 1003, 'uuser' => 'u1003'],
    'netserva.com'       => ['uid' => 1004, 'gid' => 1004, 'uuser' => 'u1004'],
    'netserva.net'       => ['uid' => 1005, 'gid' => 1005, 'uuser' => 'u1005'],
    'opensrc.org'        => ['uid' => 1006, 'gid' => 1006, 'uuser' => 'u1006'],
    'eth-os.com'         => ['uid' => 1007, 'gid' => 1007, 'uuser' => 'u1007'],
    'eth-os.net'         => ['uid' => 1008, 'gid' => 1008, 'uuser' => 'u1008'],
    'eth-os.org'         => ['uid' => 1009, 'gid' => 1009, 'uuser' => 'u1009'],
    'illareen.com'       => ['uid' => 1010, 'gid' => 1010, 'uuser' => 'u1010'],
    'illareen.net'       => ['uid' => 1011, 'gid' => 1011, 'uuser' => 'u1011'],
    'illareen.org'       => ['uid' => 1012, 'gid' => 1012, 'uuser' => 'u1012'],
];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Update vconfs U_UID/U_GID/UUSER/DUSER for gw vhosts         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$updated = 0;
$errors = 0;

foreach ($uidGidMap as $domain => $config) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🌐 {$domain}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Find vhost
    $vhost = FleetVHost::whereHas('vnode', function($q) {
        $q->where('name', 'gw');
    })->where('domain', $domain)->first();

    if (!$vhost) {
        echo "✗ Vhost not found\n\n";
        $errors++;
        continue;
    }

    // Get current values
    $currentUid = $vhost->getEnvVar('U_UID');
    $currentGid = $vhost->getEnvVar('U_GID');
    $currentUuser = $vhost->getEnvVar('UUSER');
    $currentDuser = $vhost->getEnvVar('DUSER');

    echo "Current values:\n";
    echo "  U_UID:  {$currentUid}\n";
    echo "  U_GID:  {$currentGid}\n";
    echo "  UUSER:  {$currentUuser}\n";
    echo "  DUSER:  {$currentDuser}\n";
    echo "\n";

    // Update values
    $newUid = (string)$config['uid'];
    $newGid = (string)$config['gid'];
    $newUuser = $config['uuser'];

    VConf::updateOrCreate(
        ['fleet_vhost_id' => $vhost->id, 'name' => 'U_UID'],
        ['value' => $newUid, 'category' => 'system']
    );

    VConf::updateOrCreate(
        ['fleet_vhost_id' => $vhost->id, 'name' => 'U_GID'],
        ['value' => $newGid, 'category' => 'system']
    );

    VConf::updateOrCreate(
        ['fleet_vhost_id' => $vhost->id, 'name' => 'UUSER'],
        ['value' => $newUuser, 'category' => 'system']
    );

    VConf::updateOrCreate(
        ['fleet_vhost_id' => $vhost->id, 'name' => 'DUSER'],
        ['value' => $newUuser, 'category' => 'system']
    );

    echo "Updated to:\n";
    echo "  U_UID:  {$newUid}\n";
    echo "  U_GID:  {$newGid}\n";
    echo "  UUSER:  {$newUuser}\n";
    echo "  DUSER:  {$newUuser}\n";

    if ($currentUid != $newUid || $currentGid != $newGid || $currentUuser != $newUuser) {
        echo "✓ Updated\n";
        $updated++;
    } else {
        echo "  (No changes needed)\n";
    }

    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Summary\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Updated: {$updated} vhosts\n";
echo "Errors:  {$errors}\n";
echo "Total:   " . count($uidGidMap) . " vhosts\n";
echo "\n";
echo "✅ Complete! You can now test with: php artisan chperms gw goldcoast.org\n";
