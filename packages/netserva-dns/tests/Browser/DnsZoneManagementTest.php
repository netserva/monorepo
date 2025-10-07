<?php

use Laravel\Dusk\Browser;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

uses()
    ->group('browser', 'dns-zone-management', 'priority-4');

beforeEach(function () {
    $this->provider = DnsProvider::factory()->create([
        'name' => 'Test CloudFlare',
        'type' => 'cloudflare',
    ]);
});

it('can navigate to DNS zones page', function () {
    $this->browse(function (Browser $browser) {
        $browser->visitRoute('filament.admin.pages.dashboard')
            ->assertSee('NetServa')
            ->clickLink('DNS Zones')
            ->assertPathIs('/admin/dns-zones')
            ->assertSee('DNS Zones');
    });
});

it('can create a new DNS zone through UI', function () {
    $this->browse(function (Browser $browser) {
        $browser->visitRoute('filament.admin.resources.dns-zones.index')
            ->clickLink('Create')
            ->assertPathIs('/admin/dns-zones/create')
            ->type('domain', 'test.example.com')
            ->select('dns_provider_id', $this->provider->id)
            ->type('ttl', '3600')
            ->check('is_active')
            ->press('Create')
            ->assertPathIs('/admin/dns-zones')
            ->assertSee('test.example.com')
            ->assertSee('DNS zone created successfully');
    });

    $this->assertDatabaseHas('dns_zones', [
        'domain' => 'test.example.com',
        'dns_provider_id' => $this->provider->id,
        'ttl' => 3600,
        'is_active' => true,
    ]);
});

it('can edit existing DNS zone', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'edit-test.com',
        'dns_provider_id' => $this->provider->id,
        'ttl' => 3600,
    ]);

    $this->browse(function (Browser $browser) use ($zone) {
        $browser->visitRoute('filament.admin.resources.dns-zones.edit', $zone)
            ->assertInputValue('domain', 'edit-test.com')
            ->clear('ttl')
            ->type('ttl', '7200')
            ->uncheck('is_active')
            ->press('Save changes')
            ->assertSee('DNS zone updated successfully');
    });

    expect($zone->fresh())
        ->ttl->toBe(7200)
        ->is_active->toBeFalse();
});

it('can filter DNS zones by provider', function () {
    $provider2 = DnsProvider::factory()->create(['name' => 'Other Provider']);

    DnsZone::factory()->create([
        'domain' => 'provider1.com',
        'dns_provider_id' => $this->provider->id,
    ]);

    DnsZone::factory()->create([
        'domain' => 'provider2.com',
        'dns_provider_id' => $provider2->id,
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visitRoute('filament.admin.resources.dns-zones.index')
            ->assertSee('provider1.com')
            ->assertSee('provider2.com')
            ->click('[data-testid="table-filter-dns_provider_id"]')
            ->select('[data-testid="table-filter-dns_provider_id-select"]', $this->provider->id)
            ->waitFor(1)
            ->assertSee('provider1.com')
            ->assertDontSee('provider2.com');
    });
});

it('can manage DNS records for a zone', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'records-test.com',
        'dns_provider_id' => $this->provider->id,
    ]);

    $this->browse(function (Browser $browser) use ($zone) {
        $browser->visitRoute('filament.admin.resources.dns-zones.edit', $zone)
            ->assertSee('DNS Records')
            ->click('[data-testid="dns-records-create-button"]')
            ->waitForText('Create DNS Record')
            ->select('type', 'A')
            ->type('name', 'www')
            ->type('content', '192.168.1.100')
            ->type('ttl', '300')
            ->press('Create Record')
            ->waitForText('DNS record created')
            ->assertSee('www.records-test.com')
            ->assertSee('192.168.1.100');
    });

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $zone->id,
        'type' => 'A',
        'name' => 'www.records-test.com',
        'content' => '192.168.1.100',
        'ttl' => 300,
    ]);
});

it('validates DNS zone creation form', function () {
    $this->browse(function (Browser $browser) {
        $browser->visitRoute('filament.admin.resources.dns-zones.create')
            ->press('Create')
            ->assertSee('The domain field is required')
            ->assertSee('The DNS provider field is required')
            ->type('domain', 'invalid-domain')
            ->press('Create')
            ->assertSee('The domain must be a valid domain name');
    });
});

it('can search DNS zones by domain name', function () {
    DnsZone::factory()->create([
        'domain' => 'searchable.com',
        'dns_provider_id' => $this->provider->id,
    ]);

    DnsZone::factory()->create([
        'domain' => 'other.org',
        'dns_provider_id' => $this->provider->id,
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visitRoute('filament.admin.resources.dns-zones.index')
            ->assertSee('searchable.com')
            ->assertSee('other.org')
            ->type('[data-testid="table-search-input"]', 'searchable')
            ->waitFor(1)
            ->assertSee('searchable.com')
            ->assertDontSee('other.org');
    });
});

it('can bulk delete DNS zones', function () {
    $zone1 = DnsZone::factory()->create([
        'domain' => 'bulk1.com',
        'dns_provider_id' => $this->provider->id,
    ]);

    $zone2 = DnsZone::factory()->create([
        'domain' => 'bulk2.com',
        'dns_provider_id' => $this->provider->id,
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visitRoute('filament.admin.resources.dns-zones.index')
            ->check('[data-testid="table-select-all"]')
            ->click('[data-testid="table-bulk-action-delete"]')
            ->whenAvailable('[data-testid="bulk-delete-confirmation"]', function ($modal) {
                $modal->press('Confirm');
            })
            ->waitForText('DNS zones deleted successfully')
            ->assertDontSee('bulk1.com')
            ->assertDontSee('bulk2.com');
    });

    $this->assertModelMissing($zone1);
    $this->assertModelMissing($zone2);
});

it('can view zone statistics and health status', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'stats-test.com',
        'dns_provider_id' => $this->provider->id,
    ]);

    // Create some records
    DnsRecord::factory()->count(5)->create(['dns_zone_id' => $zone->id]);

    $this->browse(function (Browser $browser) use ($zone) {
        $browser->visitRoute('filament.admin.resources.dns-zones.view', $zone)
            ->assertSee('Zone Statistics')
            ->assertSee('5 records') // Record count
            ->assertSee('Health Status')
            ->assertSee($zone->domain);
    });
});

it('can sync zone with DNS provider', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'sync-test.com',
        'dns_provider_id' => $this->provider->id,
        'external_zone_id' => 'cf-zone-123',
    ]);

    $this->browse(function (Browser $browser) use ($zone) {
        $browser->visitRoute('filament.admin.resources.dns-zones.edit', $zone)
            ->click('[data-testid="sync-zone-button"]')
            ->whenAvailable('[data-testid="sync-confirmation"]', function ($modal) {
                $modal->press('Sync Now');
            })
            ->waitForText('Zone synchronized successfully')
            ->assertSee('Last synced:');
    });
});
