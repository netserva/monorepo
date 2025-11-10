@extends('layouts.app')

@section('title', 'Blog - ' . cms_setting('name'))
@section('meta_description', 'Latest articles and insights from ' . cms_setting('name'))

@section('content')
<div class="py-12">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Page Header --}}
        <header class="mb-12 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Blog</h1>
            <p class="text-xl text-muted max-w-2xl mx-auto">
                {{ cms_setting('blog_description', 'Latest articles and insights') }}
            </p>
        </header>

        {{-- Posts Grid --}}
        @if($posts->count())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                @foreach($posts as $post)
                    <article class="bg-surface rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        {{-- Post Image --}}
                        @if($post->featured_image)
                            <a href="{{ route('cms.posts.show', $post->slug) }}" class="block">
                                <img src="{{ $post->featured_image }}"
                                     alt="{{ $post->title }}"
                                     class="w-full h-48 object-cover">
                            </a>
                        @endif

                        <div class="p-6">
                            {{-- Categories --}}
                            @if($post->categories->count())
                                <div class="flex flex-wrap gap-2 mb-3">
                                    @foreach($post->categories as $category)
                                        <a href="{{ route('cms.posts.category', $category->slug) }}"
                                           class="text-xs px-2 py-1 rounded-full bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                                            {{ $category->name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Post Title --}}
                            <h2 class="text-xl font-bold mb-2">
                                <a href="{{ route('cms.posts.show', $post->slug) }}" class="hover:text-primary transition-colors">
                                    {{ $post->title }}
                                </a>
                            </h2>

                            {{-- Excerpt --}}
                            @if($post->excerpt)
                                <p class="text-muted mb-4">{{ Str::limit($post->excerpt, 120) }}</p>
                            @endif

                            {{-- Meta --}}
                            <div class="flex items-center justify-between text-sm text-muted">
                                <time datetime="{{ $post->published_at->toIso8601String() }}">
                                    {{ $post->published_at->format('M j, Y') }}
                                </time>
                                <span>{{ $post->getReadingTime() }} min read</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="flex justify-center">
                {{ $posts->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-xl text-muted">No posts published yet. Check back soon!</p>
            </div>
        @endif
    </div>
</div>
@endsection
