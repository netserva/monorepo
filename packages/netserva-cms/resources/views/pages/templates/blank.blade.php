@extends('netserva-cms::layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Minimal template - just the content, no extras --}}
        <div class="prose prose-lg dark:prose-invert max-w-none">
            {!! $page->content !!}
        </div>
    </div>
</div>
@endsection
