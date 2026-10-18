@component('mail::message')
# Halo, {{ $name }}

Gunakan kode berikut untuk menyelesaikan login:

@component('mail::panel')
## {{ $code }}
@endcomponent

Kode berlaku selama **{{ $minutes }} menit** dan hanya bisa dipakai sekali.

Jika Anda tidak sedang mencoba login, abaikan email ini dan segera ganti password Anda.

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent