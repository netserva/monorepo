@extends('netserva-cms::layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Page Header --}}
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                {{ $page->title }}
            </h1>

            @if($page->excerpt)
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
                    {{ $page->excerpt }}
                </p>
            @endif
        </div>

        {{-- Page Content (pricing tables, etc.) --}}
        <div class="prose prose-lg dark:prose-invert max-w-none mb-12">
            {!! $page->content !!}
        </div>

        {{-- Pricing Cards Example --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            {{-- Basic Plan --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 border border-gray-200 dark:border-gray-700">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    Basic
                </h3>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-gray-900 dark:text-white">$29</span>
                    <span class="text-gray-600 dark:text-gray-400">/month</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Feature 1</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Feature 2</span>
                    </li>
                </ul>
                <a href="{{ route('cms.page.show', 'contact') }}"
                   class="block w-full bg-blue-600 text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                    Get Started
                </a>
            </div>

            {{-- Pro Plan (Featured) --}}
            <div class="bg-blue-600 rounded-lg shadow-xl p-8 relative transform scale-105">
                <div class="absolute top-0 right-0 bg-yellow-400 text-gray-900 px-3 py-1 rounded-bl-lg rounded-tr-lg text-sm font-bold">
                    POPULAR
                </div>
                <h3 class="text-2xl font-bold text-white mb-4">
                    Professional
                </h3>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-white">$79</span>
                    <span class="text-blue-100">/month</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-blue-200 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-white">Everything in Basic</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-blue-200 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-white">Pro Feature 1</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-blue-200 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-white">Pro Feature 2</span>
                    </li>
                </ul>
                <a href="{{ route('cms.page.show', 'contact') }}"
                   class="block w-full bg-white text-blue-600 text-center px-6 py-3 rounded-lg font-semibold hover:bg-blue-50 transition">
                    Get Started
                </a>
            </div>

            {{-- Enterprise Plan --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 border border-gray-200 dark:border-gray-700">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    Enterprise
                </h3>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-gray-900 dark:text-white">Custom</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Everything in Pro</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Priority Support</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Custom Solutions</span>
                    </li>
                </ul>
                <a href="{{ route('cms.page.show', 'contact') }}"
                   class="block w-full bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white text-center px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Contact Sales
                </a>
            </div>
        </div>

        {{-- Additional Info --}}
        <div class="text-center">
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                All plans include 30-day money-back guarantee
            </p>
            <a href="{{ route('cms.page.show', 'contact') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold">
                Have questions? Contact us &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
