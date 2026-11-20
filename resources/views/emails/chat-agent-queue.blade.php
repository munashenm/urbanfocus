<x-mail::message>
# Live chat

@if($message)
A customer wrote in a chat you have open.

{{ $message->body }}
@else
A customer is waiting for an agent.
@endif

@if($conversation->visitor_name)
Customer: {{ $conversation->visitor_name }}
@endif

<x-mail::button :url="$url">
Open Live Chat
</x-mail::button>
</x-mail::message>
