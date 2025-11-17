@extends('netserva-cms::layouts.app')

@section('content')
{{-- Page Header --}}
<div class="bg-white dark:bg-gray-900 py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            {{-- Categories --}}
            @if($post->categories->count() > 0)
                <div class="flex flex-wrap justify-center gap-2 mb-6">
                    @foreach($post->categories as $category)
                        <a href="{{ route('cms.blog.category', $category->slug) }}"
                           class="inline-block bg-theme-primary-light dark:dark:bg-theme-primary-darker text-theme-primary dark:dark:text-theme-primary-light px-4 py-1.5 rounded-full text-sm font-semibold hover:bg-theme-primary-lighter dark:hover:dark:bg-theme-primary-darker transition-colors">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Title --}}
            <h1 class="text-5xl md:text-6xl font-bold text-gray-900 dark:text-white mb-6 tracking-tight">
                {{ $post->title }}
            </h1>

            {{-- Excerpt --}}
            @if($post->excerpt)
                <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto leading-relaxed mb-8">
                    {{ $post->excerpt }}
                </p>
            @endif

            {{-- Author Info --}}
            @if($post->author)
                <div class="flex items-center justify-center gap-3 mb-6">
                    <img src="{{ $post->author->getFilamentAvatarUrl() ?? 'https://ui-avatars.com/api/?name=' . urlencode($post->author->name) . '&color=dc2626&background=fef2f2' }}"
                         alt="{{ $post->author->name }}"
                         class="w-12 h-12 rounded-full border-2 border-theme-primary dark:border-theme-primary">
                    <div class="text-left">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Written by</div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $post->author->name }}</div>
                    </div>
                </div>
            @endif

            {{-- Meta Info with Navigation --}}
            <div class="flex justify-between items-center gap-4 max-w-4xl mx-auto">
                {{-- Previous Post (Left Arrow) --}}
                <div class="flex-shrink-0">
                    @if($nextPost)
                        <a href="{{ route('cms.blog.show', $nextPost->slug) }}"
                           class="text-3xl hover:text-theme-primary dark:hover:dark:text-theme-primary-light transition-colors"
                           title="Next: {{ $nextPost->title }}">
                            ⬅️
                        </a>
                    @else
                        <span class="text-3xl opacity-30">⬅️</span>
                    @endif
                </div>

                {{-- Meta Info --}}
                <div class="flex flex-wrap justify-center items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        <time datetime="{{ $post->published_at?->toDateString() }}">
                            {{ $post->published_at?->format('F j, Y') }}
                        </time>
                    </div>
                    <span class="text-gray-400">&bull;</span>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $post->getReadingTime() }} min read</span>
                    </div>
                    <span class="text-gray-400">&bull;</span>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ number_format($post->word_count) }} words</span>
                    </div>
                </div>

                {{-- Next Post (Right Arrow) --}}
                <div class="flex-shrink-0">
                    @if($previousPost)
                        <a href="{{ route('cms.blog.show', $previousPost->slug) }}"
                           class="text-3xl hover:text-theme-primary dark:hover:dark:text-theme-primary-light transition-colors"
                           title="Previous: {{ $previousPost->title }}">
                            ➡️
                        </a>
                    @else
                        <span class="text-3xl opacity-30">➡️</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<article class="py-12 bg-white dark:bg-gray-900">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Featured Image --}}
        @if($post->hasMedia('featured_image'))
            <div class="mb-10 overflow-hidden rounded-2xl shadow-2xl">
                <img src="{{ $post->getFirstMediaUrl('featured_image') }}"
                     alt="{{ $post->title }}"
                     class="w-full h-auto">
            </div>
        @endif

        {{-- Post Content --}}
        <div class="prose prose-lg prose-red dark:prose-invert max-w-none mb-12
                    prose-headings:text-gray-900 dark:prose-headings:text-white
                    prose-p:text-gray-700 dark:prose-p:text-gray-300
                    prose-a:text-theme-primary dark:prose-a:dark:text-theme-primary-light
                    prose-strong:text-gray-900 dark:prose-strong:text-white
                    prose-code:text-theme-primary dark:prose-code:dark:text-theme-primary-light
                    prose-pre:bg-gray-900 dark:prose-pre:bg-black
                    prose-blockquote:border-theme-primary dark:prose-blockquote:border-theme-primary
                    prose-blockquote:text-gray-700 dark:prose-blockquote:text-gray-300">
            {!! $post->content !!}
        </div>

        {{-- Gallery --}}
        @if($post->hasMedia('gallery'))
            <div class="mb-12">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                    </svg>
                    Gallery
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($post->getMedia('gallery') as $media)
                        <div class="rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-shadow duration-300">
                            <img src="{{ $media->getUrl() }}"
                                 alt="{{ $media->name }}"
                                 class="w-full h-auto transform hover:scale-105 transition-transform duration-300">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tags --}}
        @if($post->tags->count() > 0)
            <div class="border-t border-b border-gray-200 dark:border-gray-700 py-6 mb-12">
                <div class="flex items-center flex-wrap gap-3">
                    <span class="font-bold text-gray-900 dark:text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                        </svg>
                        Tags:
                    </span>
                    @foreach($post->tags as $tag)
                        <a href="{{ route('cms.blog.tag', $tag->slug) }}"
                           class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-theme-primary hover:text-white dark:hover:bg-theme-primary transition-all duration-200 hover:shadow-md">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Related Posts --}}
        @if($relatedPosts->count() > 0)
            <div class="mt-16 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-2xl p-8 border border-gray-200 dark:border-gray-700">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-8 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                    </svg>
                    Related Posts
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($relatedPosts as $related)
                        <article class="group bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 border border-gray-200 dark:border-gray-700">
                            @if($related->hasMedia('featured_image'))
                                <a href="{{ route('cms.blog.show', $related->slug) }}" class="block overflow-hidden">
                                    <img src="{{ $related->getFirstMediaUrl('featured_image') }}"
                                         alt="{{ $related->title }}"
                                         class="w-full h-40 object-cover transform group-hover:scale-110 transition-transform duration-500">
                                </a>
                            @else
                                <div class="w-full h-40 bg-gradient-to-br from-theme-primary to-theme-primary dark:from-red-700 dark:to-red-900"></div>
                            @endif

                            <div class="p-5">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 line-clamp-2 leading-tight">
                                    <a href="{{ route('cms.blog.show', $related->slug) }}"
                                       class="hover:text-theme-primary dark:hover:dark:text-theme-primary-light transition-colors">
                                        {{ $related->title }}
                                    </a>
                                </h3>

                                <time class="text-sm text-gray-500 dark:text-gray-400 flex items-center"
                                      datetime="{{ $related->published_at?->toDateString() }}">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $related->published_at?->format('M d, Y') }}
                                </time>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Back to Blog --}}
        <div class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700 text-center">
            <a href="{{ route('cms.blog.index') }}"
               class="inline-flex items-center bg-theme-primary text-white px-8 py-3 rounded-lg font-semibold hover:bg-theme-primary dark:bg-red-700 dark:hover:bg-theme-primary transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Blog
            </a>
        </div>
    </div>
</article>
@endsection
