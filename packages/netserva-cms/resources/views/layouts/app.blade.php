<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    @php
        $pageTitle = $page->meta_title ?? $page->title ?? cms_setting('name');
        $siteTitle = str_replace(
            ['{page_title}', '{site_name}'],
            [$pageTitle, cms_setting('name')],
            cms_setting('seo_title_template', '{page_title} | {site_name}')
        );
        $description = $page->meta_description ?? cms_setting('seo_description');
        $keywords = $page->meta_keywords ?? cms_setting('seo_keywords');
    @endphp
    <title>{{ $siteTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="{{ $keywords }}">
    @if(cms_setting('seo_author'))
        <meta name="author" content="{{ cms_setting('seo_author') }}">
    @endif

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="{{ cms_setting('og_type', 'website') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(cms_setting('og_image'))
        <meta property="og:image" content="{{ cms_setting('og_image') }}">
    @endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ cms_setting('twitter_card', 'summary_large_image') }}">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if(cms_setting('twitter_handle'))
        <meta name="twitter:site" content="@{{ cms_setting('twitter_handle') }}">
    @endif
    @if(cms_setting('og_image'))
        <meta name="twitter:image" content="{{ cms_setting('og_image') }}">
    @endif

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Alpine.js Cloak (prevent FOUC) --}}
    <style>
        [x-cloak] { display: none !important; }
    </style>

    {{-- Dark Mode Script (inline to prevent FOUC) --}}
    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>

    {{-- Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    {{-- Navigation --}}
    <nav class="sticky top-0 z-50 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                {{-- Logo --}}
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ route('cms.home') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ cms_setting('name') }}
                    </a>
                </div>

                {{-- Navigation Links (Desktop - Right Side) --}}
                <div class="hidden sm:flex sm:items-center sm:space-x-1">
                    @php
                        $menu = \NetServa\Cms\Models\Menu::getByLocation('header');
                    @endphp

                    @if($menu)
                        @foreach($menu->getMenuItems() as $item)
                            <a href="{{ $item['url'] }}"
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-all duration-200"
                               @if($item['new_window'] ?? false) target="_blank" rel="noopener noreferrer" @endif>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endif

                        {{-- Dark Mode Toggle --}}
                        <button
                            type="button"
                            onclick="toggleTheme()"
                            class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500"
                            aria-label="Toggle dark mode">
                            {{-- Sun icon (shown in dark mode) --}}
                            <svg class="hidden dark:block w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                            </svg>
                            {{-- Moon icon (shown in light mode) --}}
                            <svg class="block dark:hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                            </svg>
                        </button>
                    </div>

                {{-- Mobile menu button --}}
                <div class="flex items-center sm:hidden">
                    <button type="button"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500"
                            aria-controls="mobile-menu"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            :aria-expanded="mobileMenuOpen">
                        <span class="sr-only">Toggle menu</span>
                        {{-- Hamburger icon (shown when menu is closed) --}}
                        <svg x-show="!mobileMenuOpen"
                             class="h-6 w-6"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        {{-- Close icon (shown when menu is open) --}}
                        <svg x-show="mobileMenuOpen"
                             x-cloak
                             class="h-6 w-6"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile menu panel --}}
        <div x-show="mobileMenuOpen"
             x-cloak
             @click.away="mobileMenuOpen = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="sm:hidden"
             id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                @php
                    $mobileMenu = \NetServa\Cms\Models\Menu::getByLocation('header');
                @endphp

                @if($mobileMenu)
                    @foreach($mobileMenu->getMenuItems() as $item)
                        <a href="{{ $item['url'] }}"
                           class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                           @if($item['new_window'] ?? false) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif
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
                        {{ cms_setting('name') }}
                    </h3>
                    <p class="mt-4 text-base text-gray-500 dark:text-gray-400">
                        {{ cms_setting('description') ?: config('netserva-cms.seo.site_description', 'Professional web services and solutions') }}
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
                    &copy; {{ date('Y') }} {{ cms_setting('name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    {{-- Dark Mode Toggle Script --}}
    <script>
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                // Switch to light mode
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                // Switch to dark mode
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        // Optional: Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!('theme' in localStorage)) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
