<header class="bg-surface border-b border-gray-200 dark:border-gray-700 sticky top-0 z-40">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            {{-- Logo / Site Name --}}
            <div class="flex-shrink-0">
                <a href="{{ route('cms.home') }}" class="flex items-center space-x-2">
                    @if(cms_setting('logo'))
                        <img src="{{ cms_setting('logo') }}" alt="{{ cms_setting('name') }}" class="h-8 w-auto">
                    @else
                        <span class="text-xl font-bold" style="color: var(--color-primary)">
                            {{ cms_setting('name', config('app.name')) }}
                        </span>
                    @endif
                </a>
            </div>

            {{-- Desktop Navigation --}}
            <nav class="hidden md:flex md:space-x-8" aria-label="Main navigation">
                <x-navigation />
            </nav>

            {{-- Mobile Menu Button --}}
            <div class="md:hidden">
                <button type="button"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-controls="mobile-menu"
                        aria-expanded="false"
                        x-data="{ open: false }"
                        @click="open = !open">
                    <span class="sr-only">Open main menu</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile Navigation --}}
        <div class="md:hidden" id="mobile-menu" x-data="{ open: false }" x-show="open" x-cloak>
            <div class="pt-2 pb-3 space-y-1">
                <x-navigation mobile />
            </div>
        </div>
    </div>
</header>
