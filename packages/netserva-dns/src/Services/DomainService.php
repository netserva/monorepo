<?php

namespace NetServa\Dns\Services;

use NetServa\Dns\Models\DomainRegistrar;
use NetServa\Dns\Models\DomainRegistration;

class DomainService
{
    /**
     * Check domain availability
     */
    public function checkAvailability(string $domain): array
    {
        return [
            'domain' => $domain,
            'available' => true,
            'checked_at' => now(),
        ];
    }

    /**
     * Register a new domain
     */
    public function registerDomain(string $domain, DomainRegistrar $registrar, array $contactInfo = []): DomainRegistration
    {
        return DomainRegistration::create([
            'domain' => $domain,
            'domain_registrar_id' => $registrar->id,
            'status' => 'pending',
            'contact_info' => $contactInfo,
            'registered_at' => now(),
        ]);
    }

    /**
     * Renew domain registration
     */
    public function renewDomain(DomainRegistration $registration, int $years = 1): bool
    {
        $registration->update([
            'expires_at' => $registration->expires_at->addYears($years),
            'auto_renewal' => true,
        ]);

        return true;
    }

    /**
     * Transfer domain to another registrar
     */
    public function transferDomain(DomainRegistration $registration, DomainRegistrar $newRegistrar): bool
    {
        $registration->update([
            'domain_registrar_id' => $newRegistrar->id,
            'status' => 'transfer_pending',
        ]);

        return true;
    }

    /**
     * Get domains expiring soon
     */
    public function getExpiringDomains(int $days = 30)
    {
        return DomainRegistration::where('expires_at', '<=', now()->addDays($days))
            ->where('status', 'active')
            ->get();
    }
}
