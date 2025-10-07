<?php

namespace NetServa\Cli\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Virtual Mail Management Service
 *
 * Pure PHP implementation of NetServa's addvmail functionality.
 * Replaces bash dependency with type-safe Laravel service.
 */
class VmailManagementService
{
    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        $this->remoteExecution = $remoteExecution;
    }

    /**
     * Create virtual mail user
     */
    public function createVmailUser(string $VNODE, string $email, string $password): array
    {
        Log::info('Creating virtual mail user', [
            'VNODE' => $VNODE,
            'email' => $email,
        ]);

        try {
            // Extract components
            $VHOST = substr(strstr($email, '@'), 1);
            $VUSER = substr($email, 0, strpos($email, '@'));

            // Load VHost configuration
            $configPath = base_path("../var/{$VNODE}/{$VHOST}");
            if (! file_exists($configPath)) {
                return [
                    'success' => false,
                    'error' => "VHost configuration not found: ~/.ns/var/{$VNODE}/{$VHOST}. Run 'addvhost {$VHOST} --vnode={$VNODE}' first.",
                ];
            }

            // Load environment variables
            $config = $this->loadVhostConfig($configPath);

            // Check if VHost exists in database
            $hid = $this->getVhostId($VNODE, $VHOST, $config['SQCMD']);
            if (! $hid) {
                return [
                    'success' => false,
                    'error' => "VHost {$VHOST} does not exist in database",
                ];
            }

            // Check if user already exists
            $existingUserId = $this->getVmailUserId($VNODE, $email, $config['SQCMD']);
            if ($existingUserId) {
                Log::warning('Virtual mail user already exists', ['email' => $email]);
            } else {
                // Create database entries
                $this->createVmailDatabaseEntries($VNODE, $email, $password, $hid, $config);
            }

            // Create mailbox directory structure
            $this->createMailboxStructure($VNODE, $VUSER, $config);

            // Update local configuration file
            $this->updateLocalConfigFile($VNODE, $VHOST, $email, $password);

            return [
                'success' => true,
                'details' => [
                    'email' => $email,
                    'maildir' => $config['MPATH']."/{$VUSER}/Maildir",
                    'password' => $password,
                ],
            ];

        } catch (Exception $e) {
            Log::error('Failed to create virtual mail user', [
                'VNODE' => $VNODE,
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
     * Load VHost configuration file
     */
    private function loadVhostConfig(string $configPath): array
    {
        $content = file_get_contents($configPath);
        $config = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Z_]+)=\'?([^\']+)\'?$/', $line, $matches)) {
                $config[$matches[1]] = $matches[2];
            }
        }

        return $config;
    }

    /**
     * Get VHost ID from database
     */
    private function getVhostId(string $VNODE, string $VHOST, string $sqlCmd): ?int
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT id FROM vhosts
 WHERE domain = \"{$VHOST}\"
EOS";

        $result = $this->remoteExecution->executeAsRoot($VNODE, $sql);

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        return (int) trim($result['output']);
    }

    /**
     * Get existing vmail user ID
     */
    private function getVmailUserId(string $VNODE, string $email, string $sqlCmd): ?int
    {
        $sql = "cat <<EOS | {$sqlCmd}
SELECT id FROM vmails
 WHERE user = \"{$email}\"
EOS";

        $result = $this->remoteExecution->executeAsRoot($VNODE, $sql);

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        return (int) trim($result['output']);
    }

    /**
     * Create database entries for virtual mail user
     */
    private function createVmailDatabaseEntries(string $VNODE, string $email, string $password, int $hid, array $config): void
    {
        $VUSER = substr($email, 0, strpos($email, '@'));
        $VHOST = substr(strstr($email, '@'), 1);
        $date = date('Y-m-d H:i:s');
        $mpath = $config['MPATH']."/{$VUSER}";

        // Generate dovecot password hash
        $result = $this->remoteExecution->executeAsRoot($VNODE,
            "doveadm pw -s SHA512-CRYPT -p '{$password}'"
        );

        if (! $result['success']) {
            throw new Exception('Failed to generate password hash: '.$result['error']);
        }

        $passwordHash = trim($result['output']);

        // Create vmails entry
        $vmailsSql = "cat <<EOS | {$config['SQCMD']}
INSERT INTO vmails (
        hid,
        uid,
        gid,
        active,
        user,
        home,
        password,
        updated,
        created
) VALUES (
        {$hid},
        {$config['U_UID']},
        {$config['U_GID']},
        1,
        '{$email}',
        '{$mpath}',
        '{$passwordHash}',
        '{$date}',
        '{$date}'
)
EOS";

        $result = $this->remoteExecution->executeAsRoot($VNODE, $vmailsSql);

        if (! $result['success']) {
            throw new Exception('Failed to create vmails entry: '.$result['error']);
        }

        // Get the created user ID
        $mid = $this->getVmailUserId($VNODE, $email, $config['SQCMD']);
        if (! $mid) {
            throw new Exception('Failed to retrieve created vmail user ID');
        }

        // Create vmail_log entry
        $ymd = date('Y-m-d');
        $logSql = "cat <<EOS | {$config['SQCMD']}
INSERT INTO vmail_log (
        mid,
        ymd
) VALUES (
        {$mid},
        '{$ymd}'
)
EOS";

        $this->remoteExecution->executeAsRoot($VNODE, $logSql);

        // Create valias entry (admin gets catch-all @domain, others get specific)
        $source = ($VUSER === 'admin') ? "@{$VHOST}" : $email;
        $aliasSql = "cat <<EOS | {$config['SQCMD']}
INSERT INTO valias (
        hid,
        source,
        target,
        updated,
        created
) VALUES (
        {$hid},
        '{$source}',
        '{$email}',
        '{$date}',
        '{$date}'
)
EOS";

        $this->remoteExecution->executeAsRoot($VNODE, $aliasSql);
    }

    /**
     * Create mailbox directory structure on remote server
     */
    private function createMailboxStructure(string $VNODE, string $VUSER, array $config): void
    {
        // Use MPATH from config for mail-centric structure
        $mpath = $config['MPATH']."/{$VUSER}";

        // Check if mail path base exists
        $result = $this->remoteExecution->executeAsRoot($VNODE, "test -d {$config['MPATH']}");
        if (! $result['success']) {
            throw new Exception("Mail path {$config['MPATH']} does not exist on {$VNODE}");
        }

        // Create Maildir and sieve directories
        $this->remoteExecution->executeAsRoot($VNODE,
            "mkdir -p {$mpath}/{Maildir,sieve}"
        );

        // Set up SpamProbe if not exists
        $result = $this->remoteExecution->executeAsRoot($VNODE, "test -d {$mpath}/.spamprobe");
        if (! $result['success']) {
            // Check if global spamprobe exists
            $globalExists = $this->remoteExecution->executeAsRoot($VNODE, 'test -d /etc/spamprobe');
            if (! $globalExists['success']) {
                // Download spamprobe configuration
                $this->remoteExecution->executeAsRoot($VNODE,
                    'cd /etc && wget -q https://renta.net/public/_etc_spamprobe.tgz && tar xf _etc_spamprobe.tgz >/dev/null 2>&1'
                );
            }

            // Copy spamprobe configuration
            $this->remoteExecution->executeAsRoot($VNODE,
                "mkdir {$mpath}/.spamprobe && cp -a /etc/spamprobe/* {$mpath}/.spamprobe"
            );
        }

        // Set correct ownership and permissions (use MPATH parent directory for ownership reference)
        $this->remoteExecution->executeAsRoot($VNODE,
            "chown \$(stat -c '%u:%g' {$config['MPATH']}) -R {$mpath}"
        );
        $this->remoteExecution->executeAsRoot($VNODE,
            "find {$mpath} -type d -exec chmod 00750 {} +"
        );
        $this->remoteExecution->executeAsRoot($VNODE,
            "find {$mpath} -type f -exec chmod 00640 {} +"
        );
    }

    /**
     * Update local .conf file with mail credentials
     */
    private function updateLocalConfigFile(string $VNODE, string $VHOST, string $email, string $password): void
    {
        $confPath = base_path("../var/{$VNODE}/{$VHOST}.conf");

        // Check if Mail section already exists
        $hasMailSection = false;
        if (file_exists($confPath)) {
            $content = file_get_contents($confPath);
            $hasMailSection = str_contains($content, "\nMail\n");
        }

        // Append mail information
        if ($hasMailSection) {
            // Append to existing Mail section
            file_put_contents($confPath, "Username: {$email}\nPassword: {$password}\n\n", FILE_APPEND);
        } else {
            // Create new Mail section
            $mailSection = "\nMail\n=========\n\nUsername: {$email}\nPassword: {$password}\n\n";
            file_put_contents($confPath, $mailSection, FILE_APPEND);
        }
    }
}
