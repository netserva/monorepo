@extends('layouts.app')

@section('title', $page->meta_title ?? $page->title)
@section('meta_description', $page->meta_description ?? $page->excerpt)
@section('meta_keywords', $page->meta_keywords ?? '')

@section('content')
<article class="py-12">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            {{-- Page Header --}}
            <header class="mb-8">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">{{ $page->title }}</h1>

                @if($page->excerpt)
                    <p class="text-xl text-muted">{{ $page->excerpt }}</p>
                @endif

                <div class="flex items-center text-sm text-muted mt-4">
                    <time datetime="{{ $page->updated_at->toIso8601String() }}">
                        Last updated {{ $page->updated_at->format('F j, Y') }}
                    </time>
                </div>
            </header>

            {{-- Featured Image --}}
            @if($page->featured_image)
                <div class="mb-8 rounded-lg overflow-hidden">
                    <img src="{{ $page->featured_image }}"
                         alt="{{ $page->title }}"
                         class="w-full h-auto">
                </div>
            @endif

            {{-- Page Content --}}
            <div class="prose prose-lg max-w-none dark:prose-invert">
                {!! $page->content !!}
            </div>
        </div>
    </div>
</article>
@endsection
