<?php

namespace NetServa\Mail\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Mail\Models\MailCredential;

/**
 * Virtual Mail Management Service - NetServa 3.0
 *
 * NetServa 3.0 Security Architecture:
 * - Cleartext passwords stored ONLY on workstation (encrypted at rest in mail_credentials)
 * - Remote servers receive SHA512-CRYPT hashes only (Dovecot compatible)
 * - Dual-database pattern: workstation DB + remote vnode DB
 *
 * Database-first implementation for virtual mailbox management.
 * Works with consolidated schema: vhosts, vmails, valias
 */
class VmailManagementService
{
    protected RemoteExecutionService $remoteExecution;

    protected DovecotPasswordService $passwordService;

    public function __construct(
        RemoteExecutionService $remoteExecution,
        DovecotPasswordService $passwordService
    ) {
        $this->remoteExecution = $remoteExecution;
        $this->passwordService = $passwordService;
    }

    /**
     * Create virtual mail user (NetServa 3.0 database-first)
     */
    public function createVmailUser(string $vnodeName, string $email, string $password): array
    {
        Log::info('Creating virtual mail user', [
            'vnode' => $vnodeName,
            'email' => $email,
        ]);

        try {
            // Extract components
            $domain = substr(strstr($email, '@'), 1);
            $localpart = substr($email, 0, strpos($email, '@'));

            // Get vnode from database
            $vnode = FleetVnode::where('name', $vnodeName)->first();
            if (! $vnode) {
                return [
                    'success' => false,
                    'error' => "VNode not found: {$vnodeName}. Run 'addfleet {$vnodeName}' first.",
                ];
            }

            // Check if vhost exists in fleet_vhosts
            $vhost = FleetVhost::where('vnode_id', $vnode->id)
                ->where('fqdn', $domain)
                ->first();

            if (! $vhost) {
                return [
                    'success' => false,
                    'error' => "Domain {$domain} not found. Run 'addvhost {$vnodeName} {$domain}' first.",
                ];
            }

            // SQLite database on remote vnode
            $sqlCmd = 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db';

            // Check if domain exists in vhosts table (remote database)
            if (! $this->domainExistsInVhosts($vnodeName, $domain, $sqlCmd)) {
                return [
                    'success' => false,
                    'error' => "Domain {$domain} not found in vhosts table on {$vnodeName}",
                ];
            }

            // Check if user already exists
            if ($this->vmailExists($vnodeName, $email, $sqlCmd)) {
                return [
                    'success' => false,
                    'error' => "Virtual mail user already exists: {$email}",
                ];
            }

            // Create database entries (dual-database: remote hash + local cleartext)
            $this->createVmailDatabaseEntries($vnodeName, $email, $password, $domain, $localpart, $vhost->uid, $vhost->gid, $vhost->id, $sqlCmd);

            // Create mailbox directory structure
            $this->createMailboxStructure($vnodeName, $domain, $localpart, $vhost->uid, $vhost->gid);

            return [
                'success' => true,
                'details' => [
                    'email' => $email,
                    'maildir' => "/srv/{$domain}/msg/{$localpart}/Maildir",
                    'password' => $password,
                ],
            ];

        } catch (Exception $e) {
            Log::error('Failed to create virtual mail user', [
                'vnode' => $vnodeName,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if domain exists in vhosts table
     */
    private function domainExistsInVhosts(string $vnode, string $domain, string $sqlCmd): bool
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT COUNT(*) FROM vhosts WHERE domain = '{$domain}' AND active = 1
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        return $result['success'] && trim($result['output']) === '1';
    }

    /**
     * Check if vmail user exists
     */
    private function vmailExists(string $vnode, string $email, string $sqlCmd): bool
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT COUNT(*) FROM vmails WHERE user = '{$email}'
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $sql);

        return $result['success'] && trim($result['output']) === '1';
    }

    /**
     * Create database entries for virtual mail user (NetServa 3.0 Dual-Database Security)
     *
     * Architecture:
     * 1. Generate SHA512-CRYPT hash locally (PHP native, no remote doveadm call)
     * 2. Store HASH ONLY on remote server (vmails table)
     * 3. Store CLEARTEXT on workstation (mail_credentials table, encrypted at rest)
     */
    private function createVmailDatabaseEntries(
        string $vnode,
        string $email,
        string $password,
        string $domain,
        string $localpart,
        int $uid,
        int $gid,
        int $vhostId,
        string $sqlCmd
    ): void {
        $date = date('Y-m-d H:i:s');
        $maildir = "{$domain}/msg/{$localpart}";

        // 1. Generate SHA512-CRYPT hash locally (Dovecot compatible)
        $passwordHash = $this->passwordService->generateHash($password);

        // 2. Store HASH ONLY on remote server (NO CLEARTEXT!)
        $vmailsSql = "cat <<EOS | {$sqlCmd}
INSERT INTO vmails (
    user,
    password,
    maildir,
    uid,
    gid,
    active,
    created_at,
    updated_at
) VALUES (
    '{$email}',
    '{$passwordHash}',
    '{$maildir}',
    {$uid},
    {$gid},
    1,
    '{$date}',
    '{$date}'
)
EOS";

        $result = $this->remoteExecution->executeAsRoot($vnode, $vmailsSql);

        if (! $result['success']) {
            throw new Exception('Failed to create vmails entry: '.$result['error']);
        }

        // 3. Store CLEARTEXT on workstation only (encrypted at rest via Laravel)
        MailCredential::create([
            'fleet_vhost_id' => $vhostId,
            'email' => $email,
            'cleartext_password' => $password, // Auto-encrypted by Laravel
            'notes' => "Created via addvmail on {$vnode} at ".date('Y-m-d H:i:s'),
            'last_rotated_at' => now(),
            'is_active' => true,
        ]);

        // Create valias entry (admin gets catch-all @domain, others get specific)
        $source = ($localpart === 'admin') ? "@{$domain}" : $email;
        $aliasSql = "cat <<EOS | {$sqlCmd}
INSERT INTO valias (
    source,
    target,
    active,
    created_at,
    updated_at
) VALUES (
    '{$source}',
    '{$email}',
    1,
    '{$date}',
    '{$date}'
)
EOS";

        $this->remoteExecution->executeAsRoot($vnode, $aliasSql);
    }

    /**
     * Create mailbox directory structure on remote server
     */
    private function createMailboxStructure(
        string $vnode,
        string $domain,
        string $localpart,
        int $uid,
        int $gid
    ): void {
        $mailPath = "/srv/{$domain}/msg/{$localpart}";

        // Create Maildir and sieve directories
        $result = $this->remoteExecution->executeAsRoot($vnode,
            "mkdir -p {$mailPath}/{Maildir,sieve}"
        );

        if (! $result['success']) {
            throw new Exception("Failed to create mailbox directories: {$result['error']}");
        }

        // Set up SpamProbe if not exists
        $result = $this->remoteExecution->executeAsRoot($vnode, "test -d {$mailPath}/.spamprobe");
        if (! $result['success']) {
            // Check if global spamprobe exists
            $globalExists = $this->remoteExecution->executeAsRoot($vnode, 'test -d /etc/spamprobe');
            if (! $globalExists['success']) {
                // Download spamprobe configuration
                $this->remoteExecution->executeAsRoot($vnode,
                    'cd /etc && wget -q https://renta.net/public/_etc_spamprobe.tgz && tar xf _etc_spamprobe.tgz >/dev/null 2>&1'
                );
            }

            // Copy spamprobe configuration
            $this->remoteExecution->executeAsRoot($vnode,
                "mkdir -p {$mailPath}/.spamprobe && cp -a /etc/spamprobe/* {$mailPath}/.spamprobe 2>/dev/null || true"
            );
        }

        // Set correct ownership and permissions
        $this->remoteExecution->executeAsRoot($vnode,
            "chown {$uid}:{$gid} -R {$mailPath}"
        );
        $this->remoteExecution->executeAsRoot($vnode,
            "find {$mailPath} -type d -exec chmod 00750 {} +"
        );
        $this->remoteExecution->executeAsRoot($vnode,
            "find {$mailPath} -type f -exec chmod 00640 {} +"
        );
    }
}
