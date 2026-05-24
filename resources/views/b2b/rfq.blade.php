@extends('layouts.app')

@section('title', 'Upload RFQ | Urban Focus B2B')

@section('content')
@include('b2b._form', [
    'title' => 'Upload RFQ',
    'subtitle' => 'Submit your formal request for quotation document for fast processing.',
    'type' => 'rfq',
    'messageLabel' => 'Additional notes',
    'submitLabel' => 'Upload RFQ',
])
@endsection
