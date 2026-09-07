@component('mail::message')
# Your Verification Code

Hello {{ $name }},

Please use the One-Time Password (OTP) below to {{ $reason }}.

@component('mail::panel')
{{ $otp }}
@endcomponent

Alternatively, you can click the button below to verify directly:

@component('mail::button', ['url' => $verificationUrl])
Verify Now
@endcomponent

This OTP and link will expire in **{{ $expireInMinutes }} minutes**.

If you did not make this request, no further action is required.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
