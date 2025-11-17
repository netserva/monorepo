@extends('netserva-cms::layouts.app')

@section('content')
{{-- Page Header --}}
<div class="bg-white dark:bg-gray-900 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-5xl md:text-6xl font-bold text-gray-900 dark:text-white mb-6 tracking-tight">
                Blog
            </h1>
            <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto leading-relaxed">
                Latest news, updates, and insights from the NetServa team
            </p>
        </div>
    </div>
</div>

<div class="py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
                               class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-red-500 dark:focus:border-red-500 focus:ring-red-500 dark:focus:ring-red-500">
                        <button type="submit"
                                class="bg-theme-primary text-white px-6 py-2 rounded-lg hover:bg-theme-primary dark:bg-red-700 dark:hover:bg-theme-primary transition-colors font-semibold shadow-lg hover:shadow-xl">
                            Search
                        </button>
                    </form>
                </div>

                {{-- Posts Grid --}}
                @if($posts->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        @foreach($posts as $post)
                            <article class="group flex flex-col h-full bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-2xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 border border-gray-200 dark:border-gray-700">
                                {{-- Featured Image --}}
                                @if($post->hasMedia('featured_image'))
                                    <a href="{{ route('cms.blog.show', $post->slug) }}" class="block overflow-hidden">
                                        <img src="{{ $post->getFirstMediaUrl('featured_image') }}"
                                             alt="{{ $post->title }}"
                                             class="w-full h-48 object-cover transform group-hover:scale-110 transition-transform duration-500">
                                    </a>
                                @else
                                    <div class="w-full h-48 bg-gradient-to-br from-theme-primary to-theme-primary dark:from-red-700 dark:to-red-900"></div>
                                @endif

                                <div class="p-6 flex flex-col flex-grow">
                                    {{-- Categories --}}
                                    @if($post->categories->count() > 0)
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            @foreach($post->categories as $category)
                                                <a href="{{ route('cms.blog.category', $category->slug) }}"
                                                   class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-theme-primary-lighter dark:hover:dark:bg-theme-primary-darker transition-colors">
                                                    {{ $category->name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Title --}}
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3 leading-tight">
                                        <a href="{{ route('cms.blog.show', $post->slug) }}"
                                           class="hover:text-theme-primary dark:hover:dark:text-theme-primary-light transition-colors">
                                            {{ $post->title }}
                                        </a>
                                    </h2>

                                    {{-- Excerpt --}}
                                    @if($post->excerpt)
                                        <p class="text-gray-600 dark:text-gray-300 mb-4 leading-relaxed line-clamp-3">
                                            {{ $post->excerpt }}
                                        </p>
                                    @endif

                                    {{-- Author --}}
                                    @if($post->author)
                                        <div class="flex items-center gap-2 mb-4">
                                            <img src="{{ $post->author->getFilamentAvatarUrl() ?? 'https://ui-avatars.com/api/?name=' . urlencode($post->author->name) . '&size=32&color=dc2626&background=fef2f2' }}"
                                                 alt="{{ $post->author->name }}"
                                                 class="w-8 h-8 rounded-full border border-gray-300 dark:border-gray-600">
                                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ $post->author->name }}</span>
                                        </div>
                                    @endif

                                    {{-- Meta Info (pushed to bottom) --}}
                                    <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 pt-4 mt-auto border-t border-gray-200 dark:border-gray-700">
                                        <time datetime="{{ $post->published_at?->toDateString() }}" class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $post->published_at?->format('M d, Y') }}
                                        </time>
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $post->getReadingTime() }} min read
                                        </span>
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
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center border border-gray-200 dark:border-gray-700">
                        <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-xl text-gray-600 dark:text-gray-400">
                            No posts found.
                        </p>
                        @if(request('search'))
                            <p class="text-gray-500 dark:text-gray-500 mt-2">
                                Try adjusting your search terms.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Categories --}}
                @if($categories->count() > 0)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                            </svg>
                            Categories
                        </h3>
                        <ul class="space-y-2">
                            @foreach($categories as $category)
                                <li>
                                    <a href="{{ route('cms.blog.category', $category->slug) }}"
                                       class="flex items-center justify-between py-2 px-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-gray-700 hover:text-theme-primary dark:hover:dark:text-theme-primary-light transition-colors group">
                                        <span class="font-medium">{{ $category->name }}</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full group-hover:bg-red-100 dark:group-hover:bg-red-900/30 group-hover:text-theme-primary dark:group-hover:text-red-400 transition-colors">
                                            {{ $category->posts_count }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Tags --}}
                @if($tags->count() > 0)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-theme-primary dark:dark:text-theme-primary-light" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                            </svg>
                            Popular Tags
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tags as $tag)
                                <a href="{{ route('cms.blog.tag', $tag->slug) }}"
                                   class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-theme-primary hover:text-white dark:hover:bg-theme-primary transition-all duration-200 hover:shadow-md">
                                    #{{ $tag->name }}
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
