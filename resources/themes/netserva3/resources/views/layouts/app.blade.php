<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    <title>@yield('title', cms_setting('name', config('app.name')))</title>
    <meta name="description" content="@yield('meta_description', cms_setting('tagline', ''))">
    <meta name="keywords" content="@yield('meta_keywords', '')">

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('og_title', cms_setting('name'))">
    <meta property="og:description" content="@yield('og_description', cms_setting('tagline'))">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('twitter_title', cms_setting('name'))">
    <meta name="twitter:description" content="@yield('twitter_description', cms_setting('tagline'))">

    {{-- Fonts --}}
    @php
        $headingFont = theme('typography.fonts.heading.family', 'Inter');
        $bodyFont = theme('typography.fonts.body.family', 'system-ui');
    @endphp
    @if($headingFont !== 'system-ui')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family={{ strtolower($headingFont) }}:400,500,600,700" rel="stylesheet">
    @endif

    {{-- Theme Styles --}}
    <style>
        {!! app(\NetServa\Cms\Services\ThemeService::class)->generateCssVariables() !!}
    </style>

    {{-- Additional Styles --}}
    @stack('styles')

    {{-- Scripts (head) --}}
    @stack('head-scripts')
</head>
<body class="antialiased bg-background text-text">
    {{-- Skip to main content (accessibility) --}}
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary focus:text-white focus:rounded">
        Skip to main content
    </a>

    {{-- Header --}}
    <x-header />

    {{-- Main Content --}}
    <main id="main-content" class="min-h-screen">
        @yield('content')
    </main>

    {{-- Footer --}}
    <x-footer />

    {{-- Scripts (footer) --}}
    @stack('scripts')
</body>
</html>
