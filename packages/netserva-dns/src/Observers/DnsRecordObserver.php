<?php

namespace NetServa\Dns\Observers;

use Illuminate\Support\Facades\Log;
use NetServa\Dns\Models\DnsRecord;

class DnsRecordObserver
{
    /**
     * Handle the DnsRecord "created" event.
     */
    public function created(DnsRecord $record): void
    {
        $this->syncToProvider($record, 'create');
    }

    /**
     * Handle the DnsRecord "updated" event.
     */
    public function updated(DnsRecord $record): void
    {
        $this->syncToProvider($record, 'update');
    }

    /**
     * Handle the DnsRecord "deleted" event.
     */
    public function deleted(DnsRecord $record): void
    {
        $this->syncToProvider($record, 'delete');
    }

    /**
     * Sync record to DNS provider.
     */
    protected function syncToProvider(DnsRecord $record, string $action): void
    {
        $record->load('zone.dnsProvider');
        $zone = $record->zone;
        $provider = $zone?->dnsProvider;

        if (! $provider) {
            return;
        }

        Log::info("DnsRecordObserver: {$action} triggered", [
            'record_id' => $record->id,
            'record_name' => $record->name,
            'provider' => $provider->name,
            'type' => $provider->type,
        ]);

        try {
            if ($provider->type === 'cloudflare') {
                $this->syncToCloudflare($record, $zone, $provider, $action);
            } elseif ($provider->type === 'powerdns') {
                $this->syncToPowerDns($record, $zone, $provider, $action);
            }
        } catch (\Exception $e) {
            Log::error("DnsRecordObserver sync failed: {$e->getMessage()}", [
                'record_id' => $record->id,
                'action' => $action,
            ]);
        }
    }

    /**
     * Sync to Cloudflare.
     */
    protected function syncToCloudflare(DnsRecord $record, $zone, $provider, string $action): void
    {
        $client = $provider->getClient();

        if ($action === 'create') {
            $result = $client->createRecord($zone->external_id, [
                'type' => $record->type,
                'name' => rtrim($record->name, '.'),
                'content' => trim($record->content, '"'),
                'ttl' => $record->ttl ?? 1,
                'priority' => $record->priority,
            ]);

            if (! empty($result['id'])) {
                // Update without triggering observer again
                DnsRecord::withoutEvents(function () use ($record, $result) {
                    $record->update([
                        'external_id' => $result['id'],
                        'last_synced' => now(),
                    ]);
                });

                Log::info('Cloudflare record created', ['cloudflare_id' => $result['id']]);
            }
        } elseif ($action === 'update' && $record->external_id) {
            $result = $client->updateRecord($zone->external_id, $record->external_id, [
                'type' => $record->type,
                'name' => rtrim($record->name, '.'),
                'content' => trim($record->content, '"'),
                'ttl' => $record->ttl ?? 1,
                'priority' => $record->priority,
            ]);

            if (! empty($result)) {
                DnsRecord::withoutEvents(function () use ($record) {
                    $record->update(['last_synced' => now()]);
                });

                Log::info('Cloudflare record updated');
            }
        } elseif ($action === 'delete' && $record->external_id) {
            $success = $client->deleteRecord($zone->external_id, $record->external_id);

            if ($success) {
                Log::info('Cloudflare record deleted');
            }
        }
    }

    /**
     * Sync to PowerDNS via SSH tunnel.
     */
    protected function syncToPowerDns(DnsRecord $record, $zone, $provider, string $action): void
    {
        $config = $provider->connection_config ?? [];
        if (! ($config['ssh_host'] ?? null)) {
            Log::warning('PowerDNS sync skipped: no SSH host configured', [
                'provider' => $provider->name,
            ]);

            return;
        }

        $tunnelService = app(\NetServa\Dns\Services\PowerDnsTunnelService::class);

        if ($action === 'create' || $action === 'update') {
            // Build rrset for PowerDNS PATCH
            $content = $record->content;
            if (in_array($record->type, ['MX', 'SRV']) && $record->priority) {
                $content = $record->priority.' '.$content;
            }

            $rrsets = [[
                'name' => $record->name,
                'type' => $record->type,
                'ttl' => $record->ttl ?? 300,
                'changetype' => 'REPLACE',
                'records' => [[
                    'content' => $content,
                    'disabled' => $record->disabled ?? false,
                ]],
            ]];

            $result = $tunnelService->updateRecords($provider, $zone->name, $rrsets);

            if ($result['success'] ?? false) {
                // Increment SOA serial after record update
                $tunnelService->increaseSerial($provider, $zone->name);

                DnsRecord::withoutEvents(function () use ($record) {
                    $record->update(['last_synced' => now()]);
                });

                Log::info("PowerDNS record {$action}d", [
                    'record' => $record->name,
                    'zone' => $zone->name,
                ]);
            } else {
                Log::error("PowerDNS {$action} failed", [
                    'message' => $result['message'] ?? 'Unknown error',
                    'record' => $record->name,
                ]);
            }
        } elseif ($action === 'delete') {
            $rrsets = [[
                'name' => $record->name,
                'type' => $record->type,
                'changetype' => 'DELETE',
            ]];

            $result = $tunnelService->updateRecords($provider, $zone->name, $rrsets);

            if ($result['success'] ?? false) {
                $tunnelService->increaseSerial($provider, $zone->name);
                Log::info('PowerDNS record deleted', [
                    'record' => $record->name,
                    'zone' => $zone->name,
                ]);
            } else {
                Log::error('PowerDNS delete failed', [
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        }
    }
}
