<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest configuration for NetServa CMS package tests
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;

// Load testing helpers
require_once __DIR__.'/helpers.php';

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
