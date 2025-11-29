@props(['location' => 'header', 'mobile' => false])

@php
    $menu = \NetServa\Cms\Models\Menu::getByLocation($location);
    $items = $menu?->getMenuItems() ?? [];

    $baseClasses = $mobile
        ? 'block px-3 py-2 rounded-md text-base font-medium'
        : 'inline-flex items-center px-1 pt-1 text-sm font-medium border-b-2';

    $activeClasses = $mobile
        ? 'bg-primary/10 text-primary'
        : 'border-primary text-primary';

    $inactiveClasses = $mobile
        ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
        : 'border-transparent text-gray-700 dark:text-gray-300 hover:border-gray-300 hover:text-gray-900 dark:hover:text-white';

    $dropdownClasses = 'block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700';
@endphp

@if(empty($items))
    {{-- Default menu if no menu items are configured --}}
    <a href="{{ route('cms.home') }}"
       class="{{ $baseClasses }} {{ request()->routeIs('cms.home') ? $activeClasses : $inactiveClasses }}">
        Home
    </a>
    <a href="{{ route('cms.blog.index') }}"
       class="{{ $baseClasses }} {{ request()->routeIs('cms.blog.*') ? $activeClasses : $inactiveClasses }}">
        Blog
    </a>
@else
    @foreach($items as $item)
        @php
            $hasChildren = !empty($item['children'] ?? []);
            $isActive = request()->url() === url($item['url'] ?? '#');
            $target = ($item['new_window'] ?? false) ? '_blank' : null;
        @endphp

        @if($hasChildren && !$mobile)
            {{-- Desktop dropdown menu --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open"
                        @click.away="open = false"
                        class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}">
                    {{ $item['label'] ?? 'Menu' }}
                    <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-0 z-50 mt-2 w-48 origin-top-left rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                     style="display: none;">
                    <div class="py-1">
                        {{-- Parent item as first dropdown option --}}
                        <a href="{{ url($item['url'] ?? '#') }}"
                           class="{{ $dropdownClasses }} font-medium"
                           @if($target) target="{{ $target }}" @endif>
                            {{ $item['label'] ?? 'Menu' }}
                        </a>
                        <hr class="my-1 border-gray-200 dark:border-gray-600">
                        {{-- Children --}}
                        @foreach($item['children'] as $child)
                            <a href="{{ url($child['url'] ?? '#') }}"
                               class="{{ $dropdownClasses }}"
                               @if($child['new_window'] ?? false) target="_blank" @endif>
                                {{ $child['label'] ?? 'Submenu' }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif($hasChildren && $mobile)
            {{-- Mobile: show parent and children inline --}}
            <a href="{{ url($item['url'] ?? '#') }}"
               class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}"
               @if($target) target="{{ $target }}" @endif>
                {{ $item['label'] ?? 'Menu' }}
            </a>
            @foreach($item['children'] as $child)
                <a href="{{ url($child['url'] ?? '#') }}"
                   class="{{ $baseClasses }} {{ $inactiveClasses }} pl-6"
                   @if($child['new_window'] ?? false) target="_blank" @endif>
                    {{ $child['label'] ?? 'Submenu' }}
                </a>
            @endforeach
        @else
            {{-- Simple link without children --}}
            <a href="{{ url($item['url'] ?? '#') }}"
               class="{{ $baseClasses }} {{ $isActive ? $activeClasses : $inactiveClasses }}"
               @if($target) target="{{ $target }}" @endif>
                {{ $item['label'] ?? 'Menu' }}
            </a>
        @endif
    @endforeach
@endif
