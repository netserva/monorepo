<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    <title>{{ $page->meta_title ?? $page->title ?? config('app.name') }}</title>

    @if(isset($page) && $page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif

    @if(isset($page) && $page->meta_keywords)
        <meta name="keywords" content="{{ $page->meta_keywords }}">
    @endif

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page->meta_title ?? $page->title ?? config('app.name') }}">
    @if(isset($page) && $page->meta_description)
        <meta property="og:description" content="{{ $page->meta_description }}">
    @endif
    <meta property="og:url" content="{{ url()->current() }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->meta_title ?? $page->title ?? config('app.name') }}">
    @if(isset($page) && $page->meta_description)
        <meta name="twitter:description" content="{{ $page->meta_description }}">
    @endif

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    {{-- Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans antialiased bg-white dark:bg-gray-900">
    {{-- Navigation --}}
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    {{-- Logo --}}
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ route('cms.home') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ config('app.name') }}
                        </a>
                    </div>

                    {{-- Navigation Links --}}
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        @php
                            $menu = \NetServa\Cms\Models\Menu::getByLocation('header');
                        @endphp

                        @if($menu)
                            @foreach($menu->getMenuItems() as $item)
                                <a href="{{ $item['url'] }}"
                                   class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-gray-700 dark:hover:text-gray-300"
                                   @if($item['new_window'] ?? false) target="_blank" rel="noopener noreferrer" @endif>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Mobile menu button --}}
                <div class="flex items-center sm:hidden">
                    <button type="button"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                            aria-controls="mobile-menu"
                            aria-expanded="false"
                            x-data="{ open: false }"
                            @click="open = !open">
                        <span class="sr-only">Open main menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    {{-- Page Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Column 1 --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-wider uppercase">
                        {{ config('app.name') }}
                    </h3>
                    <p class="mt-4 text-base text-gray-500 dark:text-gray-400">
                        {{ config('netserva-cms.seo.site_description', 'Professional web services and solutions') }}
                    </p>
                </div>

                {{-- Column 2 - Footer Menu --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-wider uppercase">
                        Quick Links
                    </h3>
                    @php
                        $footerMenu = \NetServa\Cms\Models\Menu::getByLocation('footer');
                    @endphp

                    @if($footerMenu)
                        <ul class="mt-4 space-y-4">
                            @foreach($footerMenu->getMenuItems() as $item)
                                <li>
                                    <a href="{{ $item['url'] }}"
                                       class="text-base text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                                       @if($item['new_window'] ?? false) target="_blank" rel="noopener noreferrer" @endif>
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Column 3 --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-wider uppercase">
                        Contact
                    </h3>
                    <p class="mt-4 text-base text-gray-500 dark:text-gray-400">
                        {!! config('netserva-cms.seo.contact_info', '') !!}
                    </p>
                </div>
            </div>

            <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-8">
                <p class="text-base text-gray-400 dark:text-gray-500 text-center">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
