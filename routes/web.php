<?php

use Illuminate\Support\Facades\Route;

/**
 * Main Application Routes
 *
 * This file provides fallback routes when the CMS package is not installed.
 * When netserva-cms is installed, it will handle the root route and pages.
 *
 * The root route is only registered if CMS frontend is disabled to avoid conflicts.
 */

// Only register fallback homepage if CMS frontend is disabled
if (! config('netserva-cms.frontend.enabled', true)) {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');
}
