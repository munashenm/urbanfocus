@extends('layouts.app')

@section('title', ($item['title'] ?? 'Case study').' | Urban Focus')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($item['challenge'] ?? $item['outcome'] ?? $item['title'] ?? ''), 160, ''))
@section('canonical', route('case-studies.show', $item['slug']))

@push('schema')
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-light mb-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('case-studies.index') }}">Case Studies</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $item['title'] }}</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold mb-2">{{ $item['title'] }}</h1>
        @if(!empty($item['industry']))
            <p class="mb-0 opacity-90">{{ $item['industry'] }}</p>
        @endif
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-8">
            @foreach(['challenge' => 'Challenge', 'requirements' => 'Requirements', 'solution' => 'Solution', 'implementation' => 'Implementation', 'outcome' => 'Outcome'] as $key => $label)
                @if(!empty($item[$key]))
                    <h2 class="h4 fw-bold mt-4">{{ $label }}</h2>
                    <p class="text-muted">{{ $item[$key] }}</p>
                @endif
            @endforeach
        </div>
        <div class="col-lg-4">
            @include('partials.corporate-procurement-cta', ['compact' => true])
        </div>
    </div>
</div>
@endsection
