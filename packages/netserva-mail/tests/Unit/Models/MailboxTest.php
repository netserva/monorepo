<?php

use NetServa\Mail\Models\MailAlias;
use NetServa\Mail\Models\Mailbox;
use NetServa\Mail\Models\MailDomain;

uses()
    ->group('unit', 'models', 'mailbox', 'priority-1');

it('can create a mailbox', function () {
    $domain = createTestMailDomain();

    $mailbox = Mailbox::factory()->create([
        'email' => 'test@example.com',
        'mail_domain_id' => $domain->id,
        'password' => 'hashed_password',
        'quota_mb' => 1000,
        'is_active' => true,
    ]);

    expect($mailbox)->toBeInstanceOf(Mailbox::class)
        ->and($mailbox->email)->toBe('test@example.com')
        ->and($mailbox->quota_mb)->toBe(1000)
        ->and($mailbox->is_active)->toBeTrue()
        ->and($mailbox->exists)->toBeTrue();
});

it('validates email format', function () {
    $mailbox = Mailbox::factory()->make([
        'email' => 'test@example.com',
    ]);

    expect($mailbox->email)->toBeValidEmail();
});

it('belongs to mail domain', function () {
    $domain = createTestMailDomain();
    $mailbox = Mailbox::factory()->create([
        'mail_domain_id' => $domain->id,
    ]);

    expect($mailbox->domain)->toBeInstanceOf(MailDomain::class)
        ->and($mailbox->domain->id)->toBe($domain->id);
});

it('has many aliases relationship', function () {
    $mailbox = Mailbox::factory()->create();

    MailAlias::factory()->count(3)->create([
        'destination' => $mailbox->email,
    ]);

    $aliases = MailAlias::where('destination', $mailbox->email)->get();

    expect($aliases)->toHaveCount(3);
});

it('hashes password when created', function () {
    $mailbox = Mailbox::factory()->create([
        'password' => 'plain_password',
    ]);

    expect($mailbox->password)->not->toBe('plain_password')
        ->and($mailbox->password)->toStartWith('$2y$');
});

it('can verify password', function () {
    $mailbox = Mailbox::factory()->create([
        'password' => 'test_password',
    ]);

    expect($mailbox->verifyPassword('test_password'))->toBeTrue()
        ->and($mailbox->verifyPassword('wrong_password'))->toBeFalse();
});

it('can find active mailboxes only', function () {
    Mailbox::factory()->create(['is_active' => true]);
    Mailbox::factory()->create(['is_active' => false]);
    Mailbox::factory()->create(['is_active' => true]);

    $activeMailboxes = Mailbox::active()->get();

    expect($activeMailboxes)->toHaveCount(2)
        ->and($activeMailboxes->first()->is_active)->toBeTrue();
});

it('can find mailboxes by domain', function () {
    $domain1 = createTestMailDomain(['domain' => 'example.com']);
    $domain2 = createTestMailDomain(['domain' => 'test.org']);

    Mailbox::factory()->create([
        'email' => 'user1@example.com',
        'mail_domain_id' => $domain1->id,
    ]);

    Mailbox::factory()->create([
        'email' => 'user2@example.com',
        'mail_domain_id' => $domain1->id,
    ]);

    Mailbox::factory()->create([
        'email' => 'user@test.org',
        'mail_domain_id' => $domain2->id,
    ]);

    $exampleMailboxes = Mailbox::byDomain('example.com')->get();

    expect($exampleMailboxes)->toHaveCount(2);
});

it('can calculate used storage', function () {
    $mailbox = Mailbox::factory()->create([
        'quota_mb' => 1000,
    ]);

    $usage = $mailbox->getStorageUsage();

    expect($usage)->toBeArray()
        ->and($usage)->toHaveKey('used_mb')
        ->and($usage)->toHaveKey('quota_mb')
        ->and($usage)->toHaveKey('percentage');
});

it('can check if quota exceeded', function () {
    $mailbox = Mailbox::factory()->create([
        'quota_mb' => 100,
    ]);

    // Mock usage of 90MB (under quota)
    $underQuota = $mailbox->isQuotaExceeded(90);

    // Mock usage of 110MB (over quota)
    $overQuota = $mailbox->isQuotaExceeded(110);

    expect($underQuota)->toBeFalse()
        ->and($overQuota)->toBeTrue();
});

it('can get maildir path', function () {
    $mailbox = Mailbox::factory()->create([
        'email' => 'test@example.com',
    ]);

    $path = $mailbox->getMaildirPath();

    expect($path)->toContain('example.com')
        ->and($path)->toContain('test@example.com');
});

it('can update last login', function () {
    $mailbox = Mailbox::factory()->create([
        'last_login_at' => null,
    ]);

    $mailbox->updateLastLogin();

    expect($mailbox->fresh()->last_login_at)->not->toBeNull()
        ->and($mailbox->fresh()->last_login_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('prevents duplicate email addresses', function () {
    Mailbox::factory()->create(['email' => 'unique@example.com']);

    expect(fn () => Mailbox::factory()->create(['email' => 'unique@example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('can generate sieve script', function () {
    $mailbox = Mailbox::factory()->create();

    $sieve = $mailbox->generateSieveScript([
        'vacation_enabled' => true,
        'vacation_message' => 'I am on vacation',
        'spam_folder' => 'Spam',
    ]);

    expect($sieve)->toContain('require ["vacation"]')
        ->and($sieve)->toContain('I am on vacation')
        ->and($sieve)->toContain('Spam');
});

it('can get message count', function () {
    $mailbox = Mailbox::factory()->create();

    $count = $mailbox->getMessageCount();

    expect($count)->toBeArray()
        ->and($count)->toHaveKey('total')
        ->and($count)->toHaveKey('unread')
        ->and($count)->toHaveKey('spam');
});

it('can archive old messages', function () {
    $mailbox = Mailbox::factory()->create();

    $result = $mailbox->archiveOldMessages(365); // Archive messages older than 1 year

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('archived_count')
        ->and($result)->toHaveKey('freed_mb');
});

it('normalizes email to lowercase', function () {
    $mailbox = Mailbox::factory()->create([
        'email' => 'TEST@EXAMPLE.COM',
    ]);

    expect($mailbox->fresh()->email)->toBe('test@example.com');
});
