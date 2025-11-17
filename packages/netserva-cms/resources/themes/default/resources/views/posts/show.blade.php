@extends('layouts.app')

@section('title', $post->meta_title ?? $post->title)
@section('meta_description', $post->meta_description ?? $post->excerpt)
@section('meta_keywords', $post->meta_keywords ?? '')
@section('og_image', $post->og_image ?? $post->featured_image)

@section('content')
<article class="py-12">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            {{-- Post Header --}}
            <header class="mb-8">
                {{-- Categories --}}
                @if($post->categories->count())
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($post->categories as $category)
                            <a href="{{ route('cms.blog.category', $category->slug) }}"
                               class="text-sm px-3 py-1 rounded-full bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <h1 class="text-4xl md:text-5xl font-bold mb-4">{{ $post->title }}</h1>

                @if($post->excerpt)
                    <p class="text-xl text-muted mb-6">{{ $post->excerpt }}</p>
                @endif

                {{-- Meta Information --}}
                <div class="flex items-center flex-wrap gap-4 text-sm text-muted">
                    <time datetime="{{ $post->published_at->toIso8601String() }}">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                    <span>•</span>
                    <span>{{ $post->getReadingTime() }} min read</span>
                    @if($post->word_count)
                        <span>•</span>
                        <span>{{ number_format($post->word_count) }} words</span>
                    @endif
                </div>
            </header>

            {{-- Featured Image --}}
            @if($post->featured_image)
                <div class="mb-8 rounded-lg overflow-hidden">
                    <img src="{{ $post->featured_image }}"
                         alt="{{ $post->title }}"
                         class="w-full h-auto">
                </div>
            @endif

            {{-- Post Content --}}
            <div class="prose prose-lg max-w-none dark:prose-invert mb-8">
                {!! $post->content !!}
            </div>

            {{-- Tags --}}
            @if($post->tags->count())
                <div class="py-6 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold mb-3">Tags:</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('cms.blog.tag', $tag->slug) }}"
                               class="text-sm px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Share Buttons --}}
            <div class="py-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold mb-3">Share this post:</h3>
                <div class="flex gap-3">
                    <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(route('cms.blog.show', $post->slug)) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                        </svg>
                        Twitter
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(route('cms.blog.show', $post->slug)) }}&title={{ urlencode($post->title) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                        LinkedIn
                    </a>
                </div>
            </div>
        </div>
    </div>
</article>

{{-- Related Posts --}}
@if(isset($relatedPosts) && $relatedPosts->count())
    <section class="py-12 bg-surface">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold mb-8">Related Posts</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($relatedPosts as $relatedPost)
                    <article class="bg-background rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        @if($relatedPost->featured_image)
                            <a href="{{ route('cms.blog.show', $relatedPost->slug) }}" class="block">
                                <img src="{{ $relatedPost->featured_image }}"
                                     alt="{{ $relatedPost->title }}"
                                     class="w-full h-48 object-cover">
                            </a>
                        @endif
                        <div class="p-6">
                            <h3 class="text-lg font-bold mb-2">
                                <a href="{{ route('cms.blog.show', $relatedPost->slug) }}" class="hover:text-primary transition-colors">
                                    {{ $relatedPost->title }}
                                </a>
                            </h3>
                            <time class="text-sm text-muted">{{ $relatedPost->published_at->format('M j, Y') }}</time>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
