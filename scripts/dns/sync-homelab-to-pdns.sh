#!/bin/bash
# Sync homelab provider zones from Laravel DB to gw PowerDNS
# This script queries the Laravel DB and pushes records to gw PowerDNS

echo "🔄 DNS Sync: Homelab Laravel DB → gw PowerDNS"
echo ""

# Export Laravel database records to temp file
php artisan tinker <<'EOPHP'
$homelab = \NetServa\Dns\Models\DnsProvider::where('name', 'homelab')->first();
$zones = $homelab->zones()->with('records')->get();

$sqlCommands = [];

foreach ($zones as $zone) {
    $zoneName = addslashes($zone->name);

    // Add zone
    $sqlCommands[] = "INSERT OR IGNORE INTO domains (name, type) VALUES ('$zoneName', 'NATIVE');";

    // Get domain_id and delete old records
    $sqlCommands[] = "DELETE FROM records WHERE domain_id = (SELECT id FROM domains WHERE name = '$zoneName');";

    // Add records
    foreach ($zone->records as $record) {
        $name = addslashes($record->name);
        $type = addslashes($record->type);
        $content = addslashes($record->content);
        $ttl = $record->ttl ?? 300;
        $prio = $record->priority ?? 0;

        $sqlCommands[] = "INSERT INTO records (domain_id, name, type, content, ttl, prio) SELECT id, '$name', '$type', '$content', $ttl, $prio FROM domains WHERE name = '$zoneName';";
    }
}

// Write to file
file_put_contents('/tmp/pdns_sync.sql', implode("\n", $sqlCommands));
echo "Generated " . count($sqlCommands) . " SQL commands\n";
EOPHP

echo ""
echo "📤 Transferring SQL to gw..."
scp /tmp/pdns_sync.sql gw:/tmp/

echo ""
echo "💾 Executing on gw PowerDNS..."
ssh gw 'sudo sqlite3 /etc/powerdns/pdns.sqlite3 < /tmp/pdns_sync.sql'

echo ""
echo "🧹 Cleaning up..."
rm /tmp/pdns_sync.sql
ssh gw 'rm /tmp/pdns_sync.sql'

echo ""
echo "✅ Sync complete!"
echo ""
echo "📊 Verifying..."
ssh gw 'sudo sqlite3 /etc/powerdns/pdns.sqlite3 "SELECT COUNT(*) as total_zones FROM domains;"'
ssh gw 'sudo sqlite3 /etc/powerdns/pdns.sqlite3 "SELECT COUNT(*) as total_records FROM records;"'
