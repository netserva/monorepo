<?php

it('has the migrate platform-profiles command registered', function () {
    expect(
        collect(explode("\n", shell_exec('cd '.base_path().' && php artisan list')))
            ->contains(fn ($line) => str_contains($line, 'migrate:platform-profiles'))
    )->toBeTrue();
});

it('command shows correct help description', function () {
    $output = shell_exec('cd '.base_path().' && php artisan migrate:platform-profiles --help');
    expect($output)->toContain('Migrate platform profile documentation from etc/ directory to database');
});

it('command can run dry-run without errors', function () {
    $output = shell_exec('cd '.base_path().' && php artisan migrate:platform-profiles --dry-run 2>&1');
    expect($output)->toContain('NetServa Platform Profiles Migration Tool');
    expect($output)->toContain('Database connection verified');
});

it('command has all expected options', function () {
    $output = shell_exec('cd '.base_path().' && php artisan migrate:platform-profiles --help');
    expect($output)->toContain('--dry-run');
    expect($output)->toContain('--force');
    expect($output)->toContain('--backup');
    expect($output)->toContain('--type');
});
