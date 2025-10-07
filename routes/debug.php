<?php

use Illuminate\Support\Facades\Route;
use Ns\Ssh\Models\SshKey;

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
