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

{{-- Page Content --}}
<div class="py-16 bg-white dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Featured Image --}}
        @if($page->hasMedia('featured_image'))
            <div class="mb-16">
                <img src="{{ $page->getFirstMediaUrl('featured_image') }}"
                     alt="{{ $page->title }}"
                     class="w-full h-auto rounded-2xl shadow-2xl">
            </div>
        @endif

        {{-- Page Content --}}
        <div class="prose prose-lg prose-red dark:prose-invert max-w-none
                    prose-headings:font-bold prose-headings:tracking-tight
                    prose-h2:text-4xl prose-h2:mt-20 prose-h2:mb-10 prose-h2:text-gray-900 dark:prose-h2:text-white prose-h2:border-b prose-h2:border-gray-200 dark:prose-h2:border-gray-700 prose-h2:pb-4
                    prose-h3:text-2xl prose-h3:mt-16 prose-h3:mb-6 prose-h3:text-red-600 dark:prose-h3:text-red-400
                    prose-h4:text-xl prose-h4:mt-10 prose-h4:mb-4 prose-h4:text-gray-800 dark:prose-h4:text-gray-200
                    prose-p:text-gray-700 dark:prose-p:text-gray-300 prose-p:leading-relaxed prose-p:mb-6
                    prose-a:text-red-600 dark:prose-a:text-red-400 prose-a:no-underline hover:prose-a:underline
                    prose-strong:text-gray-900 dark:prose-strong:text-white prose-strong:font-semibold
                    prose-ul:my-8 prose-ul:space-y-4
                    prose-li:text-gray-700 dark:prose-li:text-gray-300 prose-li:leading-relaxed
                    prose-code:text-red-600 dark:prose-code:text-red-400 prose-code:bg-gray-100 dark:prose-code:bg-gray-800 prose-code:px-2 prose-code:py-1 prose-code:rounded prose-code:text-sm prose-code:font-mono
                    prose-pre:bg-gray-900 dark:prose-pre:bg-black prose-pre:shadow-xl prose-pre:rounded-xl prose-pre:my-8 prose-pre:p-6
                    prose-blockquote:border-l-4 prose-blockquote:border-red-600 dark:prose-blockquote:border-red-400 prose-blockquote:bg-gray-50 dark:prose-blockquote:bg-gray-800 prose-blockquote:py-6 prose-blockquote:px-8 prose-blockquote:rounded-r-lg prose-blockquote:my-8
                    prose-hr:my-16 prose-hr:border-gray-300 dark:prose-hr:border-gray-700">
            {!! $page->content !!}
        </div>

        {{-- Gallery --}}
        @if($page->hasMedia('gallery'))
            <div class="mt-20">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-10 text-center">Gallery</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($page->getMedia('gallery') as $media)
                        <div class="rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-shadow duration-300 group">
                            <img src="{{ $media->getUrl() }}"
                                 alt="{{ $media->name }}"
                                 class="w-full h-auto transform group-hover:scale-105 transition-transform duration-300">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
