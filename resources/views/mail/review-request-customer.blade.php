<x-mail::customer-message :shop-name="$shopName">
# Thank you for trusting {{ $shopName }} with your vehicle

We truly appreciate the opportunity to earn your business.

If you were happy with your visit, we'd be grateful if you could take a moment to share your experience by leaving us a Google review. Your feedback helps other drivers find a repair shop they can trust.

<x-mail::button :url="$reviewUrl">
Leave a Google Review
</x-mail::button>

If you have any questions or concerns about your repair, please don't hesitate to reach out. We're always happy to help.

<x-mail::button :url="$contactUrl">
Contact Us
</x-mail::button>

@if (filled($shopPhone ?? null))
**{{ $shopPhone }}**
@endif

Thank you again,<br>
The {{ $shopName }} Team

<x-slot:subcopy>
Google review: [{{ $reviewUrl }}]({{ $reviewUrl }}) · Contact: [{{ $contactUrl }}]({{ $contactUrl }})
</x-slot:subcopy>
</x-mail::customer-message>
