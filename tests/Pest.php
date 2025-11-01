<?php

// Load CMS package testing helpers (Livewire helper function)
if (file_exists(__DIR__.'/../packages/netserva-cms/tests/helpers.php')) {
    require_once __DIR__.'/../packages/netserva-cms/tests/helpers.php';
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// All NS tests use Laravel TestCase for proper mocking and Laravel features
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(
        // Core application tests
        'Feature',
        'Unit',
        'Integration',
        'Performance',

        // Package Feature tests (need Laravel TestCase for mocking)
        '../packages/*/tests/Feature',

        // Package Unit tests (also need TestCase for factories)
        '../packages/*/tests/Unit',

        // Package Browser tests (need Laravel TestCase for authentication)
        '../packages/*/tests/Browser'
    );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

// Custom expectations for NS testing
expect()->extend('toHaveValidSshConnection', function () {
    return $this->toMatchArray([
        'success' => true,
        'latency' => expect()->toBeNumeric(),
        'connection_time' => expect()->toBeNumeric(),
    ]);
});

expect()->extend('toHaveValidDnsRecord', function () {
    return $this->toHaveKey('name')
        ->toHaveKey('type')
        ->toHaveKey('content')
        ->toHaveKey('ttl');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a test user with admin privileges.
 */
function createTestUser(array $attributes = []): \App\Models\User
{
    return \App\Models\User::factory()->create(array_merge([
        'is_admin' => true,
        'email_verified_at' => now(),
    ], $attributes));
}

/**
 * Helper function for YAML encoding in tests.
 */
function yaml_encode(array $data): string
{
    // Simple YAML encoding for test data
    $yaml = '';
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $yaml .= "{$key}:\n";
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    $yaml .= "  {$subKey}:\n";
                    foreach ($subValue as $item) {
                        if (is_array($item)) {
                            $yaml .= '    - '.json_encode($item)."\n";
                        } else {
                            $yaml .= "    - {$item}\n";
                        }
                    }
                } else {
                    $yaml .= "  {$subKey}: {$subValue}\n";
                }
            }
        } else {
            $yaml .= "{$key}: {$value}\n";
        }
    }

    return $yaml;
}
