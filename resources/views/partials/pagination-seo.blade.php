@if(!empty($paginationMeta))
@section('canonical', $paginationMeta['canonical'])
@push('head')
@if(!empty($paginationMeta['prev']))
<link rel="prev" href="{{ $paginationMeta['prev'] }}">
@endif
@if(!empty($paginationMeta['next']))
<link rel="next" href="{{ $paginationMeta['next'] }}">
@endif
@endpush
@endif
