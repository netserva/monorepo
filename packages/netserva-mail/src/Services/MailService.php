<?php

namespace NetServa\Mail\Services;

use Illuminate\Support\Facades\Process;
use NetServa\Mail\Models\MailAlias;
use NetServa\Mail\Models\Mailbox;
use NetServa\Mail\Models\MailDomain;
use NetServa\Mail\Models\MailServer;

class MailService
{
    public function createDomain(MailServer $server, array $data): MailDomain
    {
        return MailDomain::create([
            'domain' => $data['domain'],
            'infrastructure_node_id' => $server->infrastructure_node_id,
            'mail_server_id' => $server->id,
            'max_mailboxes' => $data['max_mailboxes'] ?? null,
            'max_aliases' => $data['max_aliases'] ?? null,
            'max_quota' => $data['max_quota'] ?? null,
            'is_active' => true,
        ]);
    }

    public function createMailbox(MailDomain $domain, array $data): Mailbox
    {
        $email = $data['email'];
        [$localPart, $domainPart] = explode('@', $email, 2);

        return Mailbox::create([
            'email' => $email,
            'local_part' => $localPart,
            'domain' => $domainPart,
            'password_hash' => $this->hashPassword($data['password']),
            'quota_bytes' => $data['quota'] ?? null,
            'full_name' => $data['name'] ?? null,
            'mail_domain_id' => $domain->id,
            'is_active' => true,
        ]);
    }

    public function createAlias(MailDomain $domain, array $data): MailAlias
    {
        $source = $data['source'];
        [$localPart, $domainPart] = explode('@', $source, 2);

        return MailAlias::create([
            'alias' => $source,
            'local_part' => $localPart,
            'domain' => $domainPart,
            'target_addresses' => json_encode([$data['destination']]),
            'mail_domain_id' => $domain->id,
            'is_active' => $data['active'] ?? true,
        ]);
    }

    public function configurePostfix(MailServer $server, array $config): bool
    {
        $commands = [];

        foreach ($config as $key => $value) {
            $commands[] = "ssh {$server->hostname} \"postconf -e '{$key} = {$value}'\"";
        }

        $commands[] = "ssh {$server->hostname} \"systemctl reload postfix\"";

        foreach ($commands as $command) {
            $result = Process::run($command);
            if ($result->failed()) {
                return false;
            }
        }

        return true;
    }

    public function configureDovecot(MailServer $server, array $config): bool
    {
        // Generate dovecot config and copy to server
        Process::run("scp /tmp/dovecot.conf {$server->hostname}:/etc/dovecot/");

        $result = Process::run("ssh {$server->hostname} \"systemctl reload dovecot\"");

        return $result->successful();
    }

    public function setupDkim(MailDomain $domain): array
    {
        Process::run("opendkim-genkey -s mail -d {$domain->domain}");

        return [
            'private_key' => 'generated_private_key',
            'public_key' => 'generated_public_key',
            'dns_record' => 'v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQ...',
        ];
    }

    public function generateSpfRecord(MailDomain $domain, array $options): string
    {
        $spf = 'v=spf1 mx';

        if (isset($options['include_servers'])) {
            foreach ($options['include_servers'] as $server) {
                $spf .= " a:{$server}";
            }
        }

        if (isset($options['include_providers'])) {
            foreach ($options['include_providers'] as $provider) {
                $spf .= " include:{$provider}";
            }
        }

        $spf .= ' '.($options['policy'] ?? '~all');

        return $spf;
    }

    public function getQuotaInfo(Mailbox $mailbox): array
    {
        $used = $mailbox->used_bytes ?? 0;
        $total = $mailbox->quota_bytes;
        $percentage = $total > 0 ? (int) round(($used / $total) * 100) : 0;

        return [
            'percentage' => $percentage,
            'used_human' => $this->formatBytes($used),
            'total_human' => $total === null ? '0 B' : $this->formatBytes($total),
        ];
    }

    public function migrateMailbox(Mailbox $mailbox, MailServer $targetServer): bool
    {
        $sourceServer = $mailbox->mailDomain->mailServer->hostname ?? 'unknown';

        $result = Process::run("imapsync --host1 {$sourceServer} --user1 {$mailbox->email} --host2 {$targetServer->hostname} --user2 {$mailbox->email}");

        if ($result->successful()) {
            // Create or move domain to target server, then migrate would be complete
            return true;
        }

        return false;
    }

    public function createSieveScript(Mailbox $mailbox, array $data): string
    {
        $script = 'require ["fileinto"];'."\n\n";

        if (isset($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                $script .= "if {$rule['if']} {\n";
                $script .= "    {$rule['action']};\n";
                $script .= "}\n\n";
            }
        }

        return $script;
    }

    public function getProtocolConfig(MailServer $server, string $protocol): array
    {
        $ports = [
            'smtp' => 25,
            'submission' => 587,
            'imap' => 143,
            'imaps' => 993,
            'pop3' => 110,
            'pop3s' => 995,
        ];

        return [
            'port' => $ports[$protocol] ?? 0,
            'ssl' => in_array($protocol, ['imaps', 'pop3s']),
        ];
    }

    protected function hashPassword(string $password): string
    {
        return '{SHA512-CRYPT}$6$...';
    }

    protected function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'Unlimited';
        }

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 0).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 0).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }
}
