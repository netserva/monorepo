@props(['mobile' => false])

@php
    $menuItems = \NetServa\Cms\Models\Menu::where('location', 'primary')
        ->orderBy('order')
        ->get();

    $baseClasses = $mobile
        ? 'block px-3 py-2 rounded-md text-base font-medium'
        : 'inline-flex items-center px-1 pt-1 text-sm font-medium border-b-2';

    $activeClasses = $mobile
        ? 'bg-primary/10 text-primary'
        : 'border-primary text-primary';

    $inactiveClasses = $mobile
        ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
        : 'border-transparent text-gray-700 dark:text-gray-300 hover:border-gray-300 hover:text-gray-900 dark:hover:text-white';
@endphp

@if($menuItems->isEmpty())
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
    @foreach($menuItems as $item)
        <a href="{{ $item->url }}"
           class="{{ $baseClasses }} {{ request()->url() === $item->url ? $activeClasses : $inactiveClasses }}"
           @if($item->target) target="{{ $item->target }}" @endif>
            {{ $item->title }}
        </a>
    @endforeach
@endif
