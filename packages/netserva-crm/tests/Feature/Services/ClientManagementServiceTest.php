<?php

declare(strict_types=1);

use NetServa\Crm\Models\CrmClient;
use NetServa\Crm\Services\ClientManagementService;

beforeEach(function () {
    $this->service = app(ClientManagementService::class);
});

it('can create a business client', function () {
    $result = $this->service->create([
        'company_name' => 'Acme Corp',
        'email' => 'info@acme.com',
        'status' => 'active',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['client'])->toBeInstanceOf(CrmClient::class)
        ->and($result['client']->name)->toBe('Acme Corp')
        ->and($result['client']->company_name)->toBe('Acme Corp');
});

it('can create a personal client', function () {
    $result = $this->service->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['client']->name)->toBe('John Doe')
        ->and($result['client']->first_name)->toBe('John');
});

it('fails to create client without email', function () {
    $result = $this->service->create([
        'company_name' => 'No Email Corp',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Email is required');
});

it('fails to create client with invalid email', function () {
    $result = $this->service->create([
        'company_name' => 'Bad Email Corp',
        'email' => 'not-an-email',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Invalid email');
});

it('fails to create client with duplicate email', function () {
    CrmClient::factory()->create(['email' => 'dupe@example.com']);

    $result = $this->service->create([
        'company_name' => 'Dupe Corp',
        'email' => 'dupe@example.com',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('already exists');
});

it('can list clients with filters', function () {
    CrmClient::factory()->active()->count(3)->create();
    CrmClient::factory()->prospect()->count(2)->create();

    $all = $this->service->list();
    $active = $this->service->list(['status' => 'active']);
    $prospects = $this->service->list(['status' => 'prospect']);

    expect($all)->toHaveCount(5)
        ->and($active)->toHaveCount(3)
        ->and($prospects)->toHaveCount(2);
});

it('can search clients', function () {
    CrmClient::factory()->create(['name' => 'Searchable Company']);
    CrmClient::factory()->create(['name' => 'Another Company']);

    $results = $this->service->list(['search' => 'Searchable']);

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Searchable Company');
});

it('can find client by id', function () {
    $client = CrmClient::factory()->create();

    $result = $this->service->find($client->id);

    expect($result['success'])->toBeTrue()
        ->and($result['client']->id)->toBe($client->id);
});

it('can find client by slug', function () {
    $client = CrmClient::factory()->create(['name' => 'Findable Corp']);

    $result = $this->service->find($client->slug);

    expect($result['success'])->toBeTrue()
        ->and($result['client']->id)->toBe($client->id);
});

it('can find client by email', function () {
    $client = CrmClient::factory()->create(['email' => 'findme@example.com']);

    $result = $this->service->find('findme@example.com');

    expect($result['success'])->toBeTrue()
        ->and($result['client']->id)->toBe($client->id);
});

it('returns error for nonexistent client', function () {
    $result = $this->service->find('nonexistent');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('not found');
});

it('can show client details', function () {
    $client = CrmClient::factory()->create();

    $result = $this->service->show($client->id);

    expect($result['success'])->toBeTrue()
        ->and($result['client'])->toBeInstanceOf(CrmClient::class)
        ->and($result['stats'])->toBeArray()
        ->and($result['integrations'])->toBeArray();
});

it('can update client', function () {
    $client = CrmClient::factory()->create([
        'name' => 'Original Name',
        'status' => 'active',
    ]);

    $result = $this->service->update($client->id, [
        'name' => 'Updated Name',
        'status' => 'suspended',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['client']->name)->toBe('Updated Name')
        ->and($result['client']->status)->toBe('suspended');
});

it('fails to update with duplicate email', function () {
    CrmClient::factory()->create(['email' => 'taken@example.com']);
    $client = CrmClient::factory()->create(['email' => 'original@example.com']);

    $result = $this->service->update($client->id, ['email' => 'taken@example.com']);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('already in use');
});

it('can delete client', function () {
    $client = CrmClient::factory()->create();
    $clientId = $client->id;

    $result = $this->service->delete($client->id);

    expect($result['success'])->toBeTrue()
        ->and(CrmClient::find($clientId))->toBeNull()
        ->and(CrmClient::withTrashed()->find($clientId))->not->toBeNull();
});

it('can get client statistics', function () {
    CrmClient::factory()->active()->count(5)->create();
    CrmClient::factory()->prospect()->count(3)->create();
    CrmClient::factory()->business()->count(4)->create();
    CrmClient::factory()->personal()->count(2)->create();

    $stats = $this->service->getStats();

    expect($stats)->toBeArray()
        ->and($stats['total'])->toBeGreaterThanOrEqual(8)
        ->and($stats['active'])->toBeGreaterThanOrEqual(5)
        ->and($stats['prospect'])->toBeGreaterThanOrEqual(3)
        ->and($stats['integrations'])->toBeArray();
});
