@component('mail::message')
# New contact form message

**From:** {{ $senderName }} ({{ $senderEmail }})
**Topic:** {{ $topic }}

{{ $body }}

---

Sent from the contact form at {{ config('app.url') }}/contact.
@endcomponent
