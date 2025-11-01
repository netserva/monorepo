@extends('netserva-cms::layouts.app')

@section('content')
{{-- Hero Section --}}
<div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">
                {{ $page->title }}
            </h1>

            @if($page->excerpt)
                <p class="text-xl md:text-2xl mb-8 text-blue-100">
                    {{ $page->excerpt }}
                </p>
            @endif

            <div class="flex justify-center gap-4">
                <a href="{{ route('cms.page.show', 'contact') }}"
                   class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition">
                    Get Started
                </a>
                <a href="{{ route('cms.blog.index') }}"
                   class="bg-blue-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-400 transition">
                    Learn More
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Main Content --}}
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="prose prose-lg dark:prose-invert max-w-none">
            {!! $page->content !!}
        </div>
    </div>
</div>

{{-- Features Section (if children exist) --}}
@php
    $childPages = \NetServa\Cms\Models\Page::published()
        ->where('parent_id', $page->id)
        ->orderBy('order')
        ->get();
@endphp

@if($childPages->count() > 0)
    <div class="bg-gray-50 dark:bg-gray-800 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center text-gray-900 dark:text-white mb-12">
                Our Services
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($childPages as $child)
                    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-lg p-6 hover:shadow-xl transition">
                        @if($child->hasMedia('featured_image'))
                            <img src="{{ $child->getFirstMediaUrl('featured_image') }}"
                                 alt="{{ $child->title }}"
                                 class="w-full h-48 object-cover rounded-lg mb-4">
                        @endif

                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ $child->title }}
                        </h3>

                        @if($child->excerpt)
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                {{ $child->excerpt }}
                            </p>
                        @endif

                        <a href="{{ route('cms.page.nested', [$page->slug, $child->slug]) }}"
                           class="text-blue-600 dark:text-blue-400 font-semibold hover:text-blue-800 dark:hover:text-blue-300">
                            Learn More &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

{{-- CTA Section --}}
<div class="bg-blue-600 text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold mb-4">
            Ready to Get Started?
        </h2>
        <p class="text-xl mb-8 text-blue-100">
            Contact us today to discuss your project requirements
        </p>
        <a href="{{ route('cms.page.show', 'contact') }}"
           class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition inline-block">
            Contact Us
        </a>
    </div>
</div>
@endsection
