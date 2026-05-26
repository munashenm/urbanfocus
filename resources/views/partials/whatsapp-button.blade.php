@if(config('seo.whatsapp.enabled') && config('seo.whatsapp.number'))
@php
    $waUrl = 'https://wa.me/'.preg_replace('/\D+/', '', config('seo.whatsapp.number')).'?text='.urlencode(config('seo.whatsapp.message'));
@endphp
<a href="{{ $waUrl }}" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.1 1.6 5.9L4 29l8.3-1.6c1.7.9 3.6 1.4 5.7 1.4 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 22c-1.8 0-3.5-.5-5-1.3l-.4-.2-4.9 1 1-4.8-.2-.5A8.9 8.9 0 1 1 16 25zm4.9-6.7c-.3-.1-1.7-.8-2-1-.3-.1-.5-.1-.7.1l-.9 1.1c-.2.2-.4.2-.7.1-1-.4-1.9-1-2.6-1.8-1.2-1.3-2.1-2.9-2.3-3.4-.1-.2 0-.3.1-.4l.7-.8c.2-.2.2-.4.1-.6l-.8-2c-.2-.5-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.4-1.2 1.2-1.2 2.9s1.2 3.4 1.4 3.6c.2.2 2.4 3.7 5.8 5.2.8.4 1.4.6 1.9.8.8.2 1.5.2 2.1.1.6-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4 0-.2-.2-.3-.5-.4z"/></svg>
</a>
@endif
