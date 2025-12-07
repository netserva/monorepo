<?php

declare(strict_types=1);

use NetServa\Crm\Models\CrmClient;

it('can create a business client via command', function () {
    $this->artisan('addcrm', [
        'name' => 'Test Company',
        '--company-name' => 'Test Company',
        '--email' => 'test@company.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(CrmClient::where('email', 'test@company.com')->exists())->toBeTrue();
});

it('can create a personal client via command', function () {
    $this->artisan('addcrm', [
        '--first-name' => 'John',
        '--last-name' => 'Doe',
        '--email' => 'john@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $client = CrmClient::where('email', 'john@example.com')->first();
    expect($client)->not->toBeNull()
        ->and($client->first_name)->toBe('John')
        ->and($client->last_name)->toBe('Doe');
});

it('supports dry run mode', function () {
    $this->artisan('addcrm', [
        'name' => 'Dry Run Company',
        '--email' => 'dryrun@example.com',
        '--dry-run' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(CrmClient::where('email', 'dryrun@example.com')->exists())->toBeFalse();
});

it('can list clients via shcrm', function () {
    CrmClient::factory()->count(3)->create();

    $this->artisan('shcrm')
        ->assertSuccessful();
});

it('can show client statistics via shcrm --stats', function () {
    CrmClient::factory()->active()->count(2)->create();
    CrmClient::factory()->prospect()->count(1)->create();

    $this->artisan('shcrm', ['--stats' => true])
        ->assertSuccessful();
});

it('can show specific client via shcrm', function () {
    $client = CrmClient::factory()->create(['email' => 'specific@example.com']);

    $this->artisan('shcrm', ['identifier' => $client->id])
        ->assertSuccessful();
});

it('can update client via chcrm', function () {
    $client = CrmClient::factory()->create(['status' => 'active']);

    $this->artisan('chcrm', [
        'identifier' => $client->id,
        '--status' => 'suspended',
    ])->assertSuccessful();

    expect($client->fresh()->status)->toBe('suspended');
});

it('can delete client via delcrm', function () {
    $client = CrmClient::factory()->create();

    $this->artisan('delcrm', [
        'identifier' => $client->id,
        '--yes' => true,
    ])->assertSuccessful();

    expect(CrmClient::find($client->id))->toBeNull();
});
