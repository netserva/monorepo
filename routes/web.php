<?php

use Illuminate\Support\Facades\Route;
use Ns\Ssh\Models\SshKey;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug/ssh-keys', function () {
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
});

Route::get('/debug/filament-query', function () {
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
});
