<x-mail::message>
# Daily Coaching Digest

**{{ $digest['range_label'] }}** — {{ $digest['review_count'] }} reviewed {{ Str::plural('call', $digest['review_count']) }} with coaching notes.

@if ($digest['strongest_call'])
## Strongest Call

**Customer:** {{ $digest['strongest_call']['customer_name'] }}  
**Advisor:** {{ $digest['strongest_call']['advisor_name'] }}

**Why it worked:** {{ $digest['strongest_call']['why_it_worked'] }}

<x-mail::button :url="$digest['strongest_call']['transcript_url']">
Open transcript &amp; recording
</x-mail::button>
@else
No scored calls today for a strongest-call highlight.
@endif

@if ($digest['coaching_opportunity'])
## Highest Coaching Opportunity

**Customer:** {{ $digest['coaching_opportunity']['customer_name'] }}  
**Advisor:** {{ $digest['coaching_opportunity']['advisor_name'] }}

**What could go better:** {{ $digest['coaching_opportunity']['what_to_improve'] }}

<x-mail::button :url="$digest['coaching_opportunity']['transcript_url']">
Open transcript &amp; recording
</x-mail::button>
@else
No coaching opportunities surfaced today.
@endif

<x-mail::button :url="$digest['call_intelligence_url']">
All call intelligence for this day
</x-mail::button>

<x-mail::button :url="$digest['arkademy_advisor_calls_url']">
ARKademy · phone floor training
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
