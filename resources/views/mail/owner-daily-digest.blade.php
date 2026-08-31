<x-mail::message>
# Daily shop pulse

**{{ $digest['range_label'] }}** — Sales Posted vs Cash Collected for the shop day.

@foreach ($digest['headlines'] as $line)
**{{ $line['label'] }}:** {{ $line['value'] }}  
<span style="color:#64748b;font-size:13px;">{{ $line['hint'] }}</span>

@endforeach

---

@if ($digest['reconciliation']['reconciles'])
**Payments reconcile** — cashiered cash foots to **{{ $digest['reconciliation']['sales_posted'] }}** posted sales.
@else
**Reconciliation gap** — {{ $digest['reconciliation']['delta_label'] }} vs posted sales. Cash **{{ $digest['reconciliation']['cash_collected'] }}** · reconciled **{{ $digest['reconciliation']['reconciled'] }}** · posted **{{ $digest['reconciliation']['sales_posted'] }}**.
@endif

@if (count($digest['priorities']) > 0)

**Tomorrow's pressure**

@foreach ($digest['priorities'] as $priority)
- **{{ $priority['label'] }}** ({{ $priority['count'] }}) — {{ $priority['hint'] }}
@endforeach
@else
**Queue is calm** — no unpaid pickups, approval drag, or parts blockers waiting on you.
@endif

<x-mail::button :url="$digest['financial_url']">
Payments reconciliation
</x-mail::button>

<x-mail::button :url="$digest['day_review_url'] ?? $digest['bookend_url']">
Review your day
</x-mail::button>

<x-mail::button :url="$digest['owner_pl_url']">
Owner P&amp;L
</x-mail::button>

Plan tomorrow before you leave — know why cash and posted sales differ, then name the first move.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
