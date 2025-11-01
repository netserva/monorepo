@extends('netserva-cms::layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Tag Header --}}
        <div class="text-center mb-12">
            <div class="inline-block bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-6 py-2 rounded-full mb-4">
                <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                {{ $tag->name }}
            </div>
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                Posts tagged with "{{ $tag->name }}"
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
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
                            No posts with this tag yet.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">
                {{-- All Tags --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                        All Tags
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tags as $t)
                            <a href="{{ route('cms.blog.tag', $t->slug) }}"
                               class="inline-block px-3 py-1 rounded-full text-sm {{ $t->id === $tag->id ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-600 hover:text-white' }} transition">
                                {{ $t->name }}
                            </a>
                        @endforeach
                    </div>
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
