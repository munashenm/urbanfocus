@extends('layouts.admin')

@section('page_title', 'Import WooCommerce CSV')

@section('content')
<div class="card"><div class="card-body">
    <p>Upload a WooCommerce product export CSV to import or update products. Supported columns include: ID, SKU, Name, Categories, Regular price, Sale price, Stock, In stock?, Published, Description, Short description, and Yoast SEO meta fields.</p>
    <form action="{{ route('admin.import.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label">CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
        </div>
        <button type="submit" class="btn btn-primary">Import Products</button>
    </form>
</div></div>
@endsection
