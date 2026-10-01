@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;
@endphp

@foreach (RecommendationIntent::cases() as $intent)
    .concern-priority-badge--{{ $intent->value }},
    .concern-header-intent--{{ $intent->value }},
    .concern-header-meta--{{ $intent->value }} {
        color: {{ $intent->accentColor() }};
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .concern--intent-{{ $intent->value }} .concern-header {
        background: {{ $intent->tintBackground() }};
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
@endforeach
