@extends('layouts.app')

@section('title', 'Upload RFQ | Urban Focus B2B')
@section('meta_description', 'Upload a request for quotation and Urban Focus will price networking, laptops, security and software for your South African project.')

@section('content')
@include('b2b._form', [
    'title' => 'Upload RFQ',
    'subtitle' => 'Submit your formal request for quotation document for fast processing.',
    'type' => 'rfq',
    'messageLabel' => 'Additional notes',
    'submitLabel' => 'Upload RFQ',
])
@endsection
