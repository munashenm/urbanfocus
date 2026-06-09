@props(['status'])
<span class="status-badge status-badge--{{ $status }}">{{ str_replace('_', ' ', $status) }}</span>
