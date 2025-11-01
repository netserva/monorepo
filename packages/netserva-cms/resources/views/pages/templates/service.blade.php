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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            {{-- Main Content --}}
            <div class="lg:col-span-2">
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
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 sticky top-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
                        Quick Contact
                    </h3>

                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Interested in this service? Get in touch with us today.
                    </p>

                    <a href="{{ route('cms.page.show', 'contact') }}"
                       class="block w-full bg-blue-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                        Request a Quote
                    </a>

                    {{-- Related Services --}}
                    @php
                        $relatedPages = \NetServa\Cms\Models\Page::published()
                            ->where('id', '!=', $page->id)
                            ->where('template', 'service')
                            ->limit(3)
                            ->get();
                    @endphp

                    @if($relatedPages->count() > 0)
                        <div class="mt-8">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4">
                                Other Services
                            </h4>

                            <ul class="space-y-2">
                                @foreach($relatedPages as $related)
                                    <li>
                                        <a href="{{ route('cms.page.show', $related->slug) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            {{ $related->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
