@component('mail::message')
# You're invited

You've been invited to join **{{ $organizationName }}** on Eveil.

@component('mail::button', ['url' => $acceptUrl])
Accept invitation
@endcomponent

This link expires in 7 days.

If you weren't expecting this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
