@extends('netserva-cms::layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Category Header --}}
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                {{ $category->name }}
            </h1>
            @if($category->description)
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    {{ $category->description }}
                </p>
            @endif
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                {{ $posts->total() }} {{ Str::plural('post', $posts->total()) }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-3">
                @if($posts->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        @foreach($posts as $post)
                            <article class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                                @if($post->hasMedia('featured_image'))
                                    <a href="{{ route('cms.blog.show', $post->slug) }}">
                                        <img src="{{ $post->getFirstMediaUrl('featured_image') }}"
                                             alt="{{ $post->title }}"
                                             class="w-full h-48 object-cover">
                                    </a>
                                @endif

                                <div class="p-6">
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                                        <a href="{{ route('cms.blog.show', $post->slug) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                            {{ $post->title }}
                                        </a>
                                    </h2>

                                    @if($post->excerpt)
                                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                                            {{ $post->excerpt }}
                                        </p>
                                    @endif

                                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <time datetime="{{ $post->published_at?->toDateString() }}">
                                            {{ $post->published_at?->format('M d, Y') }}
                                        </time>
                                        <span class="mx-2">&bull;</span>
                                        <span>{{ $post->getReadingTime() }} min read</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $posts->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <p class="text-xl text-gray-600 dark:text-gray-400">
                            No posts in this category yet.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">
                {{-- All Categories --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                        All Categories
                    </h3>
                    <ul class="space-y-2">
                        @foreach($categories as $cat)
                            <li>
                                <a href="{{ route('cms.blog.category', $cat->slug) }}"
                                   class="flex items-center justify-between {{ $cat->id === $category->id ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400' }}">
                                    <span>{{ $cat->name }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        ({{ $cat->posts_count }})
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Back to All Posts --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <a href="{{ route('cms.blog.index') }}" class="block text-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold">
                        View All Posts &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
