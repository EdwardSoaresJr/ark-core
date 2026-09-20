<x-mail::customer-message>
# Your sign-in code

@if (filled($customerFirstName))
Hi {{ $customerFirstName }},
@endif

Use this 6-digit code to sign in at **{{ $shopName }}**:

**{{ $plainCode }}**

This code expires in 10 minutes. If you did not ask for a code, you can ignore this email.

Thanks,<br>
{{ $shopName }}
</x-mail::customer-message>
