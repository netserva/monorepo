<?php

declare(strict_types=1);

namespace NetServa\Crm\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NetServa\Crm\CrmServiceProvider;
use NetServa\Crm\Models\CrmClient;

/**
 * Client Management Service
 *
 * ALL business logic for client CRUD operations lives here.
 * Commands and Filament resources are thin wrappers that call this service.
 */
class ClientManagementService
{
    /**
     * Create a new client
     *
     * @param  array  $data  Client data
     * @param  array  $options  Additional options
     */
    public function create(array $data, array $options = []): array
    {
        try {
            // Validate required fields
            if (empty($data['email'])) {
                return ['success' => false, 'message' => 'Email is required'];
            }

            // Validate email format
            if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            // Check for duplicate email
            if (CrmClient::where('email', $data['email'])->exists()) {
                return [
                    'success' => false,
                    'message' => "Client with email '{$data['email']}' already exists",
                ];
            }

            // Require at least first_name or company_name
            if (empty($data['first_name']) && empty($data['company_name']) && empty($data['name'])) {
                return ['success' => false, 'message' => 'First name or company name is required'];
            }

            // Auto-populate name if not provided
            if (empty($data['name'])) {
                if (! empty($data['company_name'])) {
                    $data['name'] = $data['company_name'];
                } else {
                    $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
                }
            }

            DB::beginTransaction();

            $client = CrmClient::create($data);

            DB::commit();

            return [
                'success' => true,
                'message' => "Client '{$client->name}' created successfully",
                'client' => $client->fresh(),
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Client creation failed', ['data' => $data, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to create client: '.$e->getMessage()];
        }
    }

    /**
     * List clients with optional filtering
     *
     * @param  array  $filters  Filter options
     */
    public function list(array $filters = []): Collection
    {
        $query = CrmClient::query()
            ->orderBy('name');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['business'])) {
            if ($filters['business']) {
                $query->business();
            } else {
                $query->personal();
            }
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['with_counts']) && $filters['with_counts']) {
            if (CrmServiceProvider::hasFleetIntegration()) {
                $query->withCount('vsites');
            }
            if (CrmServiceProvider::hasDomainIntegration()) {
                $query->withCount('domains');
            }
        }

        if (isset($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->get();
    }

    /**
     * Show detailed client information
     *
     * @param  int|string  $identifier  Client ID, slug, or email
     * @param  array  $options  Display options
     */
    public function show(int|string $identifier, array $options = []): array
    {
        $clientResult = $this->find($identifier);
        if (! $clientResult['success']) {
            return $clientResult;
        }

        $client = $clientResult['client'];

        $result = [
            'success' => true,
            'client' => $client,
            'stats' => [
                'vsites_count' => $client->vsite_count,
                'vnodes_count' => $client->vnode_count,
                'vhosts_count' => $client->vhost_count,
                'domains_count' => $client->domain_count,
            ],
            'integrations' => [
                'fleet' => $client->hasFleetIntegration(),
                'domains' => $client->hasDomainIntegration(),
            ],
        ];

        if ($options['with_vsites'] ?? false) {
            if ($client->hasFleetIntegration()) {
                $result['vsites'] = $client->vsites()->with('vnodes')->get();
            } else {
                $result['vsites'] = collect();
            }
        }

        if ($options['with_domains'] ?? false) {
            if ($client->hasDomainIntegration()) {
                $result['domains'] = $client->domains()->get();
            } else {
                $result['domains'] = collect();
            }
        }

        return $result;
    }

    /**
     * Update client
     *
     * @param  int|string  $identifier  Client ID, slug, or email
     * @param  array  $updates  Fields to update
     * @param  array  $options  Additional options
     */
    public function update(int|string $identifier, array $updates, array $options = []): array
    {
        try {
            $clientResult = $this->find($identifier);
            if (! $clientResult['success']) {
                return $clientResult;
            }

            $client = $clientResult['client'];

            // Check email uniqueness if changing
            if (isset($updates['email']) && $updates['email'] !== $client->email) {
                if (! filter_var($updates['email'], FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'message' => 'Invalid email format'];
                }
                if (CrmClient::where('email', $updates['email'])->where('id', '!=', $client->id)->exists()) {
                    return ['success' => false, 'message' => 'Email already in use by another client'];
                }
            }

            DB::beginTransaction();

            $client->update($updates);

            DB::commit();

            return [
                'success' => true,
                'message' => "Client '{$client->name}' updated successfully",
                'client' => $client->fresh(),
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Client update failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Update failed: '.$e->getMessage()];
        }
    }

    /**
     * Delete client
     *
     * @param  int|string  $identifier  Client ID, slug, or email
     * @param  array  $options  Delete options (force, etc.)
     */
    public function delete(int|string $identifier, array $options = []): array
    {
        try {
            $clientResult = $this->find($identifier);
            if (! $clientResult['success']) {
                return $clientResult;
            }

            $client = $clientResult['client'];

            // Check for associated resources
            $vsitesCount = $client->vsite_count;
            $domainsCount = $client->domain_count;

            if (($vsitesCount > 0 || $domainsCount > 0) && ! ($options['force'] ?? false)) {
                return [
                    'success' => false,
                    'message' => "Cannot delete client '{$client->name}' - has {$vsitesCount} VSite(s) and {$domainsCount} domain(s)",
                    'hint' => 'Use --force to delete anyway (resources will be unassigned)',
                ];
            }

            DB::beginTransaction();

            // Unassign resources if force delete
            if ($options['force'] ?? false) {
                if ($client->hasFleetIntegration() && $vsitesCount > 0) {
                    $client->vsites()->update(['customer_id' => null]);
                }
                if ($client->hasDomainIntegration() && $domainsCount > 0) {
                    $client->domains()->update(['customer_id' => null]);
                }
            }

            $clientName = $client->name;
            $client->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => "Client '{$clientName}' deleted successfully",
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Client delete failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Delete failed: '.$e->getMessage()];
        }
    }

    /**
     * Assign VSite to client (if Fleet integration available)
     */
    public function assignVsite(int $clientId, int $vsiteId): array
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            return ['success' => false, 'message' => 'Fleet integration is not available'];
        }

        try {
            $client = CrmClient::find($clientId);
            if (! $client) {
                return ['success' => false, 'message' => "Client ID {$clientId} not found"];
            }

            $vsite = \NetServa\Fleet\Models\FleetVsite::find($vsiteId);
            if (! $vsite) {
                return ['success' => false, 'message' => "VSite ID {$vsiteId} not found"];
            }

            $vsite->update(['customer_id' => $clientId]);

            return [
                'success' => true,
                'message' => "VSite '{$vsite->name}' assigned to client '{$client->name}'",
            ];

        } catch (Exception $e) {
            Log::error('VSite assignment failed', ['client_id' => $clientId, 'vsite_id' => $vsiteId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Assignment failed: '.$e->getMessage()];
        }
    }

    /**
     * Unassign VSite from client
     */
    public function unassignVsite(int $vsiteId): array
    {
        if (! CrmServiceProvider::hasFleetIntegration()) {
            return ['success' => false, 'message' => 'Fleet integration is not available'];
        }

        try {
            $vsite = \NetServa\Fleet\Models\FleetVsite::find($vsiteId);
            if (! $vsite) {
                return ['success' => false, 'message' => "VSite ID {$vsiteId} not found"];
            }

            $vsite->update(['customer_id' => null]);

            return [
                'success' => true,
                'message' => "VSite '{$vsite->name}' unassigned from client",
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Unassignment failed: '.$e->getMessage()];
        }
    }

    /**
     * Assign domain to client (if Domain integration available)
     */
    public function assignDomain(int $clientId, int $domainId): array
    {
        if (! CrmServiceProvider::hasDomainIntegration()) {
            return ['success' => false, 'message' => 'Domain integration is not available'];
        }

        try {
            $client = CrmClient::find($clientId);
            if (! $client) {
                return ['success' => false, 'message' => "Client ID {$clientId} not found"];
            }

            $domain = \App\Models\SwDomain::find($domainId);
            if (! $domain) {
                return ['success' => false, 'message' => "Domain ID {$domainId} not found"];
            }

            $domain->update(['customer_id' => $clientId]);

            return [
                'success' => true,
                'message' => "Domain '{$domain->domain_name}' assigned to client '{$client->name}'",
            ];

        } catch (Exception $e) {
            Log::error('Domain assignment failed', ['client_id' => $clientId, 'domain_id' => $domainId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Assignment failed: '.$e->getMessage()];
        }
    }

    /**
     * Unassign domain from client
     */
    public function unassignDomain(int $domainId): array
    {
        if (! CrmServiceProvider::hasDomainIntegration()) {
            return ['success' => false, 'message' => 'Domain integration is not available'];
        }

        try {
            $domain = \App\Models\SwDomain::find($domainId);
            if (! $domain) {
                return ['success' => false, 'message' => "Domain ID {$domainId} not found"];
            }

            $domain->update(['customer_id' => null]);

            return [
                'success' => true,
                'message' => "Domain '{$domain->domain_name}' unassigned from client",
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Unassignment failed: '.$e->getMessage()];
        }
    }

    /**
     * Find client by ID, slug, or email
     *
     * @param  int|string  $identifier  Client ID, slug, or email
     */
    public function find(int|string $identifier): array
    {
        if (is_numeric($identifier)) {
            $client = CrmClient::find($identifier);
        } else {
            $client = CrmClient::where('slug', $identifier)
                ->orWhere('email', $identifier)
                ->first();
        }

        if (! $client) {
            return [
                'success' => false,
                'message' => is_numeric($identifier)
                    ? "Client ID {$identifier} not found"
                    : "Client '{$identifier}' not found",
            ];
        }

        return ['success' => true, 'client' => $client];
    }

    /**
     * Get client statistics
     */
    public function getStats(): array
    {
        return [
            'total' => CrmClient::count(),
            'active' => CrmClient::active()->count(),
            'prospect' => CrmClient::prospect()->count(),
            'suspended' => CrmClient::suspended()->count(),
            'cancelled' => CrmClient::cancelled()->count(),
            'business' => CrmClient::business()->count(),
            'personal' => CrmClient::personal()->count(),
            'integrations' => [
                'fleet' => CrmServiceProvider::hasFleetIntegration(),
                'domains' => CrmServiceProvider::hasDomainIntegration(),
                'core' => CrmServiceProvider::hasCoreIntegration(),
            ],
        ];
    }
}
