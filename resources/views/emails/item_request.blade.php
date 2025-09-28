@component('mail::message')
# Item Request Created

Request Number: {{ $itemRequest->request_number }}
Created At: {{ $itemRequest->created_at->format('d M Y H:i') }}

@component('mail::button', ['url' => url('/item-requests/'.$itemRequest->id)])
View Request
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent