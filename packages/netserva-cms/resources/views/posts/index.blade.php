@extends('netserva-cms::layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Blog Header --}}
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                Blog
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-400">
                Latest news, updates, and insights
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-3">
                {{-- Search Bar --}}
                <div class="mb-8">
                    <form action="{{ route('cms.blog.index') }}" method="GET" class="flex gap-2">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search posts..."
                               class="flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                            Search
                        </button>
                    </form>
                </div>

                {{-- Posts Grid --}}
                @if($posts->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        @foreach($posts as $post)
                            <article class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                                {{-- Featured Image --}}
                                @if($post->hasMedia('featured_image'))
                                    <a href="{{ route('cms.blog.show', $post->slug) }}">
                                        <img src="{{ $post->getFirstMediaUrl('featured_image') }}"
                                             alt="{{ $post->title }}"
                                             class="w-full h-48 object-cover">
                                    </a>
                                @endif

                                <div class="p-6">
                                    {{-- Categories --}}
                                    @if($post->categories->count() > 0)
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            @foreach($post->categories as $category)
                                                <a href="{{ route('cms.blog.category', $category->slug) }}"
                                                   class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                                    {{ $category->name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Title --}}
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                                        <a href="{{ route('cms.blog.show', $post->slug) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                            {{ $post->title }}
                                        </a>
                                    </h2>

                                    {{-- Excerpt --}}
                                    @if($post->excerpt)
                                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                                            {{ $post->excerpt }}
                                        </p>
                                    @endif

                                    {{-- Meta Info --}}
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
                            No posts found.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">
                {{-- Categories --}}
                @if($categories->count() > 0)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            Categories
                        </h3>
                        <ul class="space-y-2">
                            @foreach($categories as $category)
                                <li>
                                    <a href="{{ route('cms.blog.category', $category->slug) }}"
                                       class="flex items-center justify-between text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                                        <span>{{ $category->name }}</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            ({{ $category->posts_count }})
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Tags --}}
                @if($tags->count() > 0)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            Tags
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tags as $tag)
                                <a href="{{ route('cms.blog.tag', $tag->slug) }}"
                                   class="inline-block bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-3 py-1 rounded-full text-sm hover:bg-blue-600 hover:text-white transition">
                                    {{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
