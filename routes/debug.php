<?php

use Illuminate\Support\Facades\Route;
use Ns\Ssh\Models\SshKey;

/**
 * Debug Routes
 *
 * These routes are registered under /admin/debug/* and provide
 * debugging tools and information for development.
 *
 * Registered in bootstrap/app.php under admin prefix.
 */
Route::get('/ssh-keys', function () {
    $keys = SshKey::all();

    return [
        'count' => $keys->count(),
        'keys' => $keys->map(function ($key) {
            return [
                'id' => $key->id,
                'name' => $key->name,
                'key_type' => $key->key_type,
                'is_active' => $key->is_active,
                'ssh_host_id' => $key->ssh_host_id,
                'created_at' => $key->created_at?->toDateTimeString(),
            ];
        }),
    ];
})->name('debug.ssh-keys');

Route::get('/filament-query', function () {
    // Test the exact query pattern Filament would use
    $model = \Ns\Ssh\Models\SshKey::class;
    $query = $model::query();

    // Get the results
    $results = $query->get();

    return [
        'model_class' => $model,
        'query_sql' => $query->toSql(),
        'count' => $results->count(),
        'first_3_records' => $results->take(3)->map(function ($key) {
            return [
                'id' => $key->id,
                'name' => $key->name,
                'key_type' => $key->key_type,
                'is_active' => $key->is_active,
                'deleted_at' => $key->deleted_at,
            ];
        }),
    ];
})->name('debug.filament-query');
