<?php

use NetServa\Ops\Models\Incident;
use NetServa\Ops\Models\MonitoringCheck;

uses()
    ->group('unit', 'models', 'monitoring-check', 'priority-1');

it('can create a monitoring check', function () {
    $check = MonitoringCheck::factory()->create([
        'name' => 'HTTP Check - Website',
        'check_type' => 'http',
        'target' => 'https://example.com',
        'check_interval_seconds' => 300,
        'timeout_seconds' => 30,
        'is_active' => true,
        'status' => 'up',
    ]);

    expect($check)->toBeInstanceOf(MonitoringCheck::class)
        ->and($check->name)->toBe('HTTP Check - Website')
        ->and($check->check_type)->toBe('http')
        ->and($check->target)->toBe('https://example.com')
        ->and($check->is_active)->toBeTrue()
        ->and($check->status)->toBe('up')
        ->and($check->exists)->toBeTrue();
});

it('casts check_config to array', function () {
    $config = ['method' => 'GET', 'expected_status' => 200];

    $check = MonitoringCheck::factory()->create([
        'check_config' => $config,
    ]);

    expect($check->check_config)->toBeArray()
        ->and($check->check_config['method'])->toBe('GET')
        ->and($check->check_config['expected_status'])->toBe(200);
});

it('casts alert_contacts to array', function () {
    $contacts = ['admin@example.com', 'ops@example.com'];

    $check = MonitoringCheck::factory()->create([
        'alert_contacts' => $contacts,
    ]);

    expect($check->alert_contacts)->toBeArray()
        ->and($check->alert_contacts)->toHaveCount(2)
        ->and($check->alert_contacts)->toContain('admin@example.com');
});

it('has many incidents relationship', function () {
    $check = MonitoringCheck::factory()->create();

    Incident::factory()->count(2)->create([
        'monitoring_check_id' => $check->id,
    ]);

    expect($check->incidents)->toHaveCount(2)
        ->and($check->incidents->first())->toBeInstanceOf(Incident::class);
});

it('can check if monitoring check is healthy', function () {
    $healthyCheck = MonitoringCheck::factory()->create([
        'status' => 'up',
        'in_maintenance' => false,
    ]);

    $downCheck = MonitoringCheck::factory()->create([
        'status' => 'down',
        'in_maintenance' => false,
    ]);

    $maintenanceCheck = MonitoringCheck::factory()->create([
        'status' => 'up',
        'in_maintenance' => true,
    ]);

    expect($healthyCheck->isHealthy())->toBeTrue()
        ->and($downCheck->isHealthy())->toBeFalse()
        ->and($maintenanceCheck->isHealthy())->toBeFalse();
});

it('can check if monitoring check is in alert state', function () {
    $downCheck = MonitoringCheck::factory()->create(['status' => 'down']);
    $upCheck = MonitoringCheck::factory()->create(['status' => 'up']);
    $degradedCheck = MonitoringCheck::factory()->create(['status' => 'degraded']);

    expect($downCheck->isInAlertState())->toBeTrue()
        ->and($upCheck->isInAlertState())->toBeFalse()
        ->and($degradedCheck->isInAlertState())->toBeFalse();
});

it('can get next check time', function () {
    $check = MonitoringCheck::factory()->create([
        'last_check_at' => now()->subMinutes(3),
        'check_interval_seconds' => 300, // 5 minutes
        'is_active' => true,
    ]);

    $nextCheck = $check->getNextCheckTime();

    expect($nextCheck)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($nextCheck->diffInMinutes(now()))->toBeLessThan(3);
});

it('returns now for next check time if never checked', function () {
    $check = MonitoringCheck::factory()->create([
        'last_check_at' => null,
        'is_active' => true,
    ]);

    $nextCheck = $check->getNextCheckTime();

    expect($nextCheck->diffInSeconds(now()))->toBeLessThan(5);
});

it('can check if monitoring check is due', function () {
    $overdueCheck = MonitoringCheck::factory()->create([
        'last_check_at' => now()->subMinutes(10),
        'check_interval_seconds' => 300, // 5 minutes
        'is_active' => true,
    ]);

    $recentCheck = MonitoringCheck::factory()->create([
        'last_check_at' => now()->subMinutes(2),
        'check_interval_seconds' => 300, // 5 minutes
        'is_active' => true,
    ]);

    $inactiveCheck = MonitoringCheck::factory()->create([
        'last_check_at' => now()->subMinutes(10),
        'check_interval_seconds' => 300,
        'is_active' => false,
    ]);

    expect($overdueCheck->isDue())->toBeTrue()
        ->and($recentCheck->isDue())->toBeFalse()
        ->and($inactiveCheck->isDue())->toBeFalse();
});

it('can find active checks only', function () {
    MonitoringCheck::factory()->create(['is_active' => true]);
    MonitoringCheck::factory()->create(['is_active' => false]);
    MonitoringCheck::factory()->create(['is_active' => true]);

    $activeChecks = MonitoringCheck::active()->get();

    expect($activeChecks)->toHaveCount(2)
        ->and($activeChecks->first()->is_active)->toBeTrue();
});

it('can find due checks', function () {
    // Due check (last checked 10 minutes ago, interval 5 minutes)
    MonitoringCheck::factory()->create([
        'is_active' => true,
        'last_check_at' => now()->subMinutes(10),
        'check_interval_seconds' => 300,
        'next_check_at' => now()->subMinutes(5),
    ]);

    // Not due check (last checked 2 minutes ago, interval 5 minutes)
    MonitoringCheck::factory()->create([
        'is_active' => true,
        'last_check_at' => now()->subMinutes(2),
        'check_interval_seconds' => 300,
        'next_check_at' => now()->addMinutes(3),
    ]);

    // Inactive check
    MonitoringCheck::factory()->create([
        'is_active' => false,
        'next_check_at' => now()->subMinutes(5),
    ]);

    $dueChecks = MonitoringCheck::due()->get();

    expect($dueChecks)->toHaveCount(1);
});

it('can find checks by type', function () {
    MonitoringCheck::factory()->create(['check_type' => 'http']);
    MonitoringCheck::factory()->create(['check_type' => 'ping']);
    MonitoringCheck::factory()->create(['check_type' => 'http']);

    $httpChecks = MonitoringCheck::ofType('http')->get();

    expect($httpChecks)->toHaveCount(2)
        ->and($httpChecks->first()->check_type)->toBe('http');
});

it('can find checks by status', function () {
    MonitoringCheck::factory()->create(['status' => 'up']);
    MonitoringCheck::factory()->create(['status' => 'down']);
    MonitoringCheck::factory()->create(['status' => 'up']);

    $upChecks = MonitoringCheck::withStatus('up')->get();

    expect($upChecks)->toHaveCount(2)
        ->and($upChecks->first()->status)->toBe('up');
});

it('can find checks in maintenance', function () {
    MonitoringCheck::factory()->create(['in_maintenance' => true]);
    MonitoringCheck::factory()->create(['in_maintenance' => false]);
    MonitoringCheck::factory()->create(['in_maintenance' => true]);

    $maintenanceChecks = MonitoringCheck::inMaintenance()->get();

    expect($maintenanceChecks)->toHaveCount(2)
        ->and($maintenanceChecks->first()->in_maintenance)->toBeTrue();
});

it('can get status color attribute', function () {
    $upCheck = MonitoringCheck::factory()->create(['status' => 'up']);
    $downCheck = MonitoringCheck::factory()->create(['status' => 'down']);
    $degradedCheck = MonitoringCheck::factory()->create(['status' => 'degraded']);
    $maintenanceCheck = MonitoringCheck::factory()->create(['status' => 'maintenance']);

    expect($upCheck->status_color)->toBe('success')
        ->and($downCheck->status_color)->toBe('danger')
        ->and($degradedCheck->status_color)->toBe('warning')
        ->and($maintenanceCheck->status_color)->toBe('info');
});

it('can get formatted uptime attribute', function () {
    $check = MonitoringCheck::factory()->create(['uptime_percentage' => 99.567]);

    expect($check->formatted_uptime)->toBe('99.57%');
});

it('handles null uptime percentage', function () {
    $check = MonitoringCheck::factory()->create(['uptime_percentage' => null]);

    expect($check->formatted_uptime)->toBe('0.00%');
});

it('validates check configuration structure', function () {
    $httpConfig = [
        'method' => 'GET',
        'expected_status' => 200,
        'timeout' => 30,
        'headers' => ['User-Agent' => 'NetServa Monitor'],
    ];

    $check = MonitoringCheck::factory()->create([
        'check_type' => 'http',
        'check_config' => $httpConfig,
    ]);

    expect($check->check_config)->toHaveKey('method')
        ->and($check->check_config)->toHaveKey('expected_status')
        ->and($check->check_config['headers'])->toBeArray();
});

it('handles boolean casting properly', function () {
    $check = MonitoringCheck::factory()->create([
        'is_active' => 1,
        'alert_enabled' => 0,
        'in_maintenance' => 1,
    ]);

    expect($check->is_active)->toBeTrue()
        ->and($check->alert_enabled)->toBeFalse()
        ->and($check->in_maintenance)->toBeTrue();
});
