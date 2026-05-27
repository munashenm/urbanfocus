@extends('layouts.app')

@section('title', ($archiveTitle ?? 'Blog').' | Urban Focus')
@section('meta_description', $archiveDescription ?? config('blog.index_description'))
@if(!empty($pagination['canonical']))
@section('canonical', $pagination['canonical'])
@endif

@section('content')
<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb breadcrumb-light mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('blog.index') }}" class="text-white-50">Blog</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">{{ $archiveTitle }}</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold mb-2">{{ $archiveTitle }}</h1>
        @if(!empty($archiveDescription))
            <p class="mb-0 opacity-75">{{ $archiveDescription }}</p>
        @endif
        @if(!empty($author) && $author->bio)
            <p class="mb-0 mt-2 small opacity-75">{{ $author->bio }}</p>
        @endif
    </div>
</div>

<div class="container py-5">
    @if($articles->count())
        <div class="row g-4">
            @foreach($articles as $article)
                <div class="col-md-6 col-lg-4">
                    @include('partials.article-card', ['article' => $article])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $articles->links() }}</div>
    @else
        <div class="text-center py-5 text-muted">No articles found in this archive yet.</div>
    @endif
</div>
@endsection

@push('schema')
<script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->breadcrumbSchema([
    ['name' => 'Home', 'url' => route('home')],
    ['name' => 'Blog', 'url' => route('blog.index')],
    ['name' => $archiveTitle, 'url' => url()->current()],
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
