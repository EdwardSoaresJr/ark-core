@php
    use App\Ark\Operations\RepairOrders\RecommendationPresentation;
@endphp

@foreach (RecommendationPresentation::catalog() as $presentation)
    .{{ $presentation->badgeClass() }},
    .concern-header-intent--{{ $presentation->key() }},
    .concern-header-meta--{{ $presentation->key() }} {
        color: {{ $presentation->accentColor() }};
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .{{ $presentation->pdfScopeClass() }} .concern-header {
        background: {{ $presentation->tintBackground() }};
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
@endforeach
