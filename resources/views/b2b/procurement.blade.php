@extends('layouts.app')

@section('title', 'Corporate & Government Procurement | Urban Focus')

@section('content')
@include('b2b._form', [
    'title' => 'Corporate & Government Procurement',
    'subtitle' => 'Formal quotes, vendor registration, and supply for businesses and government departments.',
    'type' => 'procurement',
    'messageLabel' => 'Procurement requirements & tender details',
    'submitLabel' => 'Submit Procurement Enquiry',
])
@endsection
