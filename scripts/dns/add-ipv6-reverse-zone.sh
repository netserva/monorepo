#!/bin/bash
# Add IPv6 reverse zone f.b.a.0.9.e.5.a.a.d.f.ip6.arpa. to gw PowerDNS

ZONE_NAME="f.b.a.0.9.e.5.a.a.d.f.ip6.arpa."

echo "Adding IPv6 reverse zone: ${ZONE_NAME}"

# Add zone to PowerDNS
ssh gw "sudo sqlite3 /etc/powerdns/pdns.sqlite3 <<'EOSQL'
-- Insert the domain/zone
INSERT INTO domains (name, type, notified_serial, last_check)
VALUES ('${ZONE_NAME}', 'NATIVE', NULL, NULL);

-- Get the domain_id
-- Insert SOA record
INSERT INTO records (domain_id, name, type, content, ttl, prio)
SELECT id, '${ZONE_NAME}', 'SOA', 'ns1.goldcoast.org. hostmaster.goldcoast.org. 2025101101 7200 3600 604800 300', 3600, 0
FROM domains WHERE name = '${ZONE_NAME}';

-- Insert NS record
INSERT INTO records (domain_id, name, type, content, ttl, prio)
SELECT id, '${ZONE_NAME}', 'NS', 'ns1.goldcoast.org.', 3600, 0
FROM domains WHERE name = '${ZONE_NAME}';

-- Insert PTR record for mail.goldcoast.org (fdaa:5e90:abf::f3a)
INSERT INTO records (domain_id, name, type, content, ttl, prio)
SELECT id, 'a.3.f.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.f.b.a.0.9.e.5.a.a.d.f.ip6.arpa.', 'PTR', 'mail.goldcoast.org.', 3600, 0
FROM domains WHERE name = '${ZONE_NAME}';
EOSQL
"

echo ""
echo "Verifying zone was added..."
ssh gw "sudo sqlite3 /etc/powerdns/pdns.sqlite3 'SELECT name FROM domains WHERE name = \"${ZONE_NAME}\";'"

echo ""
echo "Verifying records were added..."
ssh gw "sudo sqlite3 /etc/powerdns/pdns.sqlite3 'SELECT r.name, r.type, r.content FROM records r JOIN domains d ON r.domain_id = d.id WHERE d.name = \"${ZONE_NAME}\" ORDER BY r.type;'"

echo ""
echo "Done! Zone and records added to gw PowerDNS."
