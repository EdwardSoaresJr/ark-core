@if (! ($line['type'] === 'note' && (
    array_key_exists('visible_to_customer', $line)
        ? ! ($line['visible_to_customer'] ?? false)
        : ($line['is_private'] ?? false)
)))
@php
    $isGrouped = (bool) ($grouped ?? false);
    $lineType = (string) ($line['type'] ?? '');
    $usesInlineTypeBadge = in_array($lineType, ['labor', 'part', 'sublet'], true);
    $spanDescriptionColumn = $usesInlineTypeBadge || $isGrouped;
@endphp
<div class="line-item {{ $line['type'] === 'note' ? 'line-item--note' : '' }} {{ $spanDescriptionColumn ? 'line-item--grouped' : '' }}">
    @if ($line['type'] === 'note')
        <div class="line-desc-col line-desc-col--note">
            <div class="line-note-label">{{ $line['type_label'] }}</div>
            @include('operations.documents.partials._customer-line-description', [
                'line' => $line,
                'grouped' => $isGrouped,
                'workGroupTitle' => $workGroupTitle ?? '',
                'variant' => 'pdf',
            ])
        </div>
    @else
        @unless ($spanDescriptionColumn)
            <div class="line-type-col">{{ $line['type_label'] }}</div>
        @endunless
        <div @class([
            'line-desc-col',
            'line-desc-col--grouped' => $spanDescriptionColumn,
        ])>
            @include('operations.documents.partials._customer-line-description', [
                'line' => $line,
                'grouped' => $isGrouped,
                'workGroupTitle' => $workGroupTitle ?? '',
                'variant' => 'pdf',
            ])
        </div>
        <div class="line-col">{{ $line['quantity'] }}</div>
        <div class="line-col">{{ $line['unit_price'] }}</div>
        <div class="line-col">{{ $line['subtotal'] }}</div>
        <div class="line-col line-col--muted">{{ ($line['shop_fee_cents'] ?? 0) > 0 ? $line['shop_fee'] : '—' }}</div>
        <div class="line-col line-col--muted">{{ ($line['tax_cents'] ?? 0) > 0 ? $line['tax'] : '—' }}</div>
        <div class="line-col line-col--total">{{ $line['total'] }}</div>
    @endif
</div>
@endif
