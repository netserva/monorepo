<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FilamentGuestMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $authEnabled = config('filament.auth_enabled', true);
        $isAuthenticated = Auth::check();

        // Debug logging during tests
        if (app()->environment('testing')) {
            \Log::info('FilamentGuestMode: auth_enabled='.($authEnabled ? 'true' : 'false').
                      ', is_authenticated='.($isAuthenticated ? 'true' : 'false'));
        }

        // If Filament auth is disabled and no user is authenticated
        if (! $authEnabled && ! $isAuthenticated) {
            // Create or get a guest user
            $guestUser = User::firstOrCreate(
                ['email' => 'guest@localhost'],
                [
                    'name' => 'Guest User',
                    'password' => bcrypt('guest'),
                    'email_verified_at' => now(),
                ]
            );

            // Log in the guest user
            Auth::login($guestUser);

            if (app()->environment('testing')) {
                \Log::info('FilamentGuestMode: Created and logged in guest user');
            }
        }

        return $next($request);
    }
}
