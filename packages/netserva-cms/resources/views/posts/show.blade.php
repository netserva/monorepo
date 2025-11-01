@extends('netserva-cms::layouts.app')

@section('content')
<article class="py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumbs --}}
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
                        <a href="{{ route('cms.blog.index') }}" class="text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                            Blog
                        </a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $post->title }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        {{-- Post Header --}}
        <header class="mb-8">
            {{-- Categories --}}
            @if($post->categories->count() > 0)
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($post->categories as $category)
                        <a href="{{ route('cms.blog.category', $category->slug) }}"
                           class="inline-block bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm font-semibold hover:bg-blue-200 dark:hover:bg-blue-800 transition">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Title --}}
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                {{ $post->title }}
            </h1>

            {{-- Excerpt --}}
            @if($post->excerpt)
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-6">
                    {{ $post->excerpt }}
                </p>
            @endif

            {{-- Meta Info --}}
            <div class="flex items-center text-gray-600 dark:text-gray-400 space-x-4">
                <time datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->published_at?->format('F j, Y') }}
                </time>
                <span>&bull;</span>
                <span>{{ $post->getReadingTime() }} min read</span>
                <span>&bull;</span>
                <span>{{ number_format($post->word_count) }} words</span>
            </div>
        </header>

        {{-- Featured Image --}}
        @if($post->hasMedia('featured_image'))
            <div class="mb-8">
                <img src="{{ $post->getFirstMediaUrl('featured_image') }}"
                     alt="{{ $post->title }}"
                     class="w-full h-auto rounded-lg shadow-xl">
            </div>
        @endif

        {{-- Post Content --}}
        <div class="prose prose-lg dark:prose-invert max-w-none mb-12">
            {!! $post->content !!}
        </div>

        {{-- Gallery --}}
        @if($post->hasMedia('gallery'))
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Gallery</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($post->getMedia('gallery') as $media)
                        <div class="rounded-lg overflow-hidden shadow-lg">
                            <img src="{{ $media->getUrl() }}"
                                 alt="{{ $media->name }}"
                                 class="w-full h-auto">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tags --}}
        @if($post->tags->count() > 0)
            <div class="border-t border-b border-gray-200 dark:border-gray-700 py-6 mb-12">
                <div class="flex items-center flex-wrap gap-2">
                    <span class="font-semibold text-gray-900 dark:text-white">Tags:</span>
                    @foreach($post->tags as $tag)
                        <a href="{{ route('cms.blog.tag', $tag->slug) }}"
                           class="inline-block bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-3 py-1 rounded-full text-sm hover:bg-blue-600 hover:text-white transition">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Related Posts --}}
        @if($relatedPosts->count() > 0)
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    Related Posts
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($relatedPosts as $related)
                        <article class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                            @if($related->hasMedia('featured_image'))
                                <a href="{{ route('cms.blog.show', $related->slug) }}">
                                    <img src="{{ $related->getFirstMediaUrl('featured_image') }}"
                                         alt="{{ $related->title }}"
                                         class="w-full h-32 object-cover">
                                </a>
                            @endif

                            <div class="p-4">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                                    <a href="{{ route('cms.blog.show', $related->slug) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                        {{ $related->title }}
                                    </a>
                                </h3>

                                <time class="text-sm text-gray-500 dark:text-gray-400" datetime="{{ $related->published_at?->toDateString() }}">
                                    {{ $related->published_at?->format('M d, Y') }}
                                </time>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Back to Blog --}}
        <div class="mt-12 text-center">
            <a href="{{ route('cms.blog.index') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Blog
            </a>
        </div>
    </div>
</article>
@endsection
