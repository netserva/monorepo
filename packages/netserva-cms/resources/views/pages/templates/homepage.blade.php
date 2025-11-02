@extends('netserva-cms::layouts.app')

@section('content')
{{-- Page Header --}}
<div class="bg-white dark:bg-gray-900 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-5xl md:text-6xl font-bold text-gray-900 dark:text-white mb-6 tracking-tight">
                {{ $page->title }}
            </h1>

            @if($page->excerpt)
                <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto leading-relaxed">
                    {{ $page->excerpt }}
                </p>
            @endif
        </div>
    </div>
</div>

{{-- Main Content --}}
<div class="py-16 bg-white dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="prose prose-lg prose-red dark:prose-invert max-w-none
                    prose-headings:text-gray-900 dark:prose-headings:text-white
                    prose-p:text-gray-600 dark:prose-p:text-gray-300
                    prose-a:text-red-600 dark:prose-a:text-red-400
                    prose-strong:text-gray-900 dark:prose-strong:text-white">
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
    <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    Our Services
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                    Comprehensive solutions for your infrastructure needs
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($childPages as $child)
                    <div class="group bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-2xl p-8 transition-all duration-300 transform hover:-translate-y-2 border border-gray-200 dark:border-gray-700">
                        @if($child->hasMedia('featured_image'))
                            <div class="mb-6 overflow-hidden rounded-lg">
                                <img src="{{ $child->getFirstMediaUrl('featured_image') }}"
                                     alt="{{ $child->title }}"
                                     class="w-full h-48 object-cover transform group-hover:scale-110 transition-transform duration-300">
                            </div>
                        @else
                            {{-- Icon placeholder --}}
                            <div class="mb-6 w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-lg flex items-center justify-center">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        @endif

                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors">
                            {{ $child->title }}
                        </h3>

                        @if($child->excerpt)
                            <p class="text-gray-600 dark:text-gray-300 mb-6 leading-relaxed">
                                {{ $child->excerpt }}
                            </p>
                        @endif

                        <a href="{{ route('cms.page.nested', [$page->slug, $child->slug]) }}"
                           class="inline-flex items-center text-red-600 dark:text-red-400 font-semibold hover:text-red-800 dark:hover:text-red-300 transition-colors">
                            Learn More
                            <svg class="w-5 h-5 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                            </svg>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

{{-- CTA Section with gradient --}}
<div class="relative bg-gradient-to-r from-red-600 via-red-700 to-red-900 dark:from-red-700 dark:via-red-800 dark:to-red-950 text-white py-20 overflow-hidden">
    {{-- Decorative elements --}}
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-72 h-72 bg-white rounded-full mix-blend-overlay filter blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-red-400 rounded-full mix-blend-overlay filter blur-3xl"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-6">
            Ready to Get Started?
        </h2>
        <p class="text-xl mb-10 text-red-100 dark:text-gray-300 max-w-2xl mx-auto">
            Contact us today to discuss your infrastructure requirements and discover how we can help
        </p>
        <a href="{{ route('cms.page.show', 'contact') }}"
           class="inline-block bg-white text-red-600 px-10 py-4 rounded-lg font-bold text-lg hover:bg-red-50 hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-200">
            Contact Us Today
        </a>
    </div>
</div>
@endsection
