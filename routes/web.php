<?php

use Illuminate\Support\Facades\Route;

/**
 * Main Application Routes
 *
 * This file provides fallback routes when the CMS package is not installed.
 * When netserva-cms is installed, it will handle the root route and pages.
 *
 * The netserva-cms package handles the root route via PageController@home,
 * so no fallback route is needed here.
 */

// NetServa CMS handles the root route - no fallback needed
