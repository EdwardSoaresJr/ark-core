<x-mail::customer-message>
# Your verification code

Use this 6-digit code to continue booking at **{{ $shopName }}**:

**{{ $plainCode }}**

This code expires in {{ $ttlMinutes }} minutes. If you did not ask for a code, you can ignore this email.

Thanks,<br>
{{ $shopName }}
</x-mail::customer-message>
