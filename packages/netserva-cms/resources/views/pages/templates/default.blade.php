@extends('netserva-cms::layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumbs --}}
        @if(isset($parent))
            <nav class="flex mb-8" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('cms.home') }}" class="text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                            Home
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <span class="mx-2 text-gray-400">/</span>
                            <a href="{{ route('cms.page.show', $parent->slug) }}" class="text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                {{ $parent->title }}
                            </a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <span class="mx-2 text-gray-400">/</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $page->title }}</span>
                        </div>
                    </li>
                </ol>
            </nav>
        @endif

        {{-- Page Header --}}
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                {{ $page->title }}
            </h1>

            @if($page->excerpt)
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    {{ $page->excerpt }}
                </p>
            @endif
        </div>

        {{-- Featured Image --}}
        @if($page->hasMedia('featured_image'))
            <div class="mb-8">
                <img src="{{ $page->getFirstMediaUrl('featured_image') }}"
                     alt="{{ $page->title }}"
                     class="w-full h-auto rounded-lg shadow-lg">
            </div>
        @endif

        {{-- Page Content --}}
        <div class="prose prose-lg dark:prose-invert max-w-none">
            {!! $page->content !!}
        </div>

        {{-- Gallery --}}
        @if($page->hasMedia('gallery'))
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Gallery</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($page->getMedia('gallery') as $media)
                        <div class="rounded-lg overflow-hidden shadow-lg">
                            <img src="{{ $media->getUrl() }}"
                                 alt="{{ $media->name }}"
                                 class="w-full h-auto">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
