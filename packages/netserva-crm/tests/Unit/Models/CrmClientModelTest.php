<?php

declare(strict_types=1);

use NetServa\Crm\Models\CrmClient;

it('can create a client using factory', function () {
    $client = CrmClient::factory()->create();

    expect($client)->toBeInstanceOf(CrmClient::class)
        ->and($client->id)->toBeInt()
        ->and($client->name)->not->toBeEmpty()
        ->and($client->email)->not->toBeEmpty();
});

it('generates slug automatically', function () {
    $client = CrmClient::factory()->create([
        'name' => 'Acme Corporation',
        'slug' => null,
    ]);

    expect($client->slug)->toStartWith('acme-corporation');
});

it('generates unique slugs', function () {
    $client1 = CrmClient::factory()->create(['name' => 'Test Company']);
    $client2 = CrmClient::factory()->create(['name' => 'Test Company']);

    expect($client1->slug)->not->toBe($client2->slug);
});

it('can create business client', function () {
    $client = CrmClient::factory()->business()->create();

    expect($client->company_name)->not->toBeEmpty()
        ->and($client->is_business)->toBeTrue();
});

it('can create personal client', function () {
    $client = CrmClient::factory()->personal()->create();

    expect($client->first_name)->not->toBeEmpty()
        ->and($client->last_name)->not->toBeEmpty()
        ->and($client->company_name)->toBeNull()
        ->and($client->is_business)->toBeFalse();
});

it('has correct status methods', function () {
    $active = CrmClient::factory()->active()->create();
    $prospect = CrmClient::factory()->prospect()->create();
    $suspended = CrmClient::factory()->suspended()->create();
    $cancelled = CrmClient::factory()->cancelled()->create();

    expect($active->isActive())->toBeTrue()
        ->and($prospect->isProspect())->toBeTrue()
        ->and($suspended->isSuspended())->toBeTrue()
        ->and($cancelled->isCancelled())->toBeTrue();
});

it('has working scopes', function () {
    // Create with explicit status to avoid random status assignment
    CrmClient::factory()->active()->business()->count(3)->create();
    CrmClient::factory()->prospect()->business()->count(2)->create();
    CrmClient::factory()->suspended()->personal()->count(1)->create();

    expect(CrmClient::active()->count())->toBe(3)
        ->and(CrmClient::prospect()->count())->toBe(2)
        ->and(CrmClient::business()->count())->toBe(5)  // 3 active + 2 prospect
        ->and(CrmClient::personal()->count())->toBe(1);
});

it('can search clients', function () {
    CrmClient::factory()->create([
        'name' => 'Searchable Company',
        'email' => 'test@searchable.com',
    ]);
    CrmClient::factory()->create([
        'name' => 'Other Company',
        'email' => 'other@example.com',
    ]);

    expect(CrmClient::search('Searchable')->count())->toBe(1)
        ->and(CrmClient::search('searchable.com')->count())->toBe(1);
});

it('formats full address correctly', function () {
    $client = CrmClient::factory()->create([
        'address_line_1' => '123 Test Street',
        'city' => 'Sydney',
        'state' => 'NSW',
        'postcode' => '2000',
        'country' => 'AU',
    ]);

    expect($client->full_address)->toContain('123 Test Street')
        ->and($client->full_address)->toContain('Sydney')
        ->and($client->full_address)->toContain('NSW');
});

it('formats ABN correctly', function () {
    $client = CrmClient::factory()->create([
        'abn' => '12345678901',
    ]);

    expect($client->formatted_abn)->toBe('12 345 678 901');
});

it('handles metadata correctly', function () {
    $client = CrmClient::factory()->create();

    $client->setMeta('billing.plan', 'premium');
    $client->setMeta('notes.internal', 'VIP client');
    $client->save();

    $client->refresh();

    expect($client->getMeta('billing.plan'))->toBe('premium')
        ->and($client->getMeta('notes.internal'))->toBe('VIP client')
        ->and($client->hasMeta('billing.plan'))->toBeTrue()
        ->and($client->hasMeta('nonexistent'))->toBeFalse();
});

it('soft deletes clients', function () {
    $client = CrmClient::factory()->create();
    $clientId = $client->id;

    $client->delete();

    expect(CrmClient::find($clientId))->toBeNull()
        ->and(CrmClient::withTrashed()->find($clientId))->not->toBeNull();
});

it('generates display name from company if set', function () {
    $client = CrmClient::factory()->create([
        'name' => '',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'company_name' => 'Acme Corp',
    ]);

    // The boot method should have set name to company_name
    expect($client->name)->toBe('Acme Corp');
});

it('generates display name from personal name if no company', function () {
    $client = CrmClient::factory()->create([
        'name' => '',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'company_name' => null,
    ]);

    expect($client->name)->toBe('John Doe');
});

it('returns full name accessor correctly', function () {
    $client = CrmClient::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    expect($client->full_name)->toBe('John Doe');
});
