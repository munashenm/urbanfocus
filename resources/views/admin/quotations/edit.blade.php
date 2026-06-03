@extends('layouts.admin')

@section('page_title', 'Edit '.$quotation->quotation_number)

@section('content')
@include('admin.quotations._form', ['quotation' => $quotation, 'prefill' => [], 'defaultValidUntil' => $defaultValidUntil, 'defaultTerms' => $defaultTerms])
@endsection
