@php
    $variant = $variant ?? 'pdf';
    $isPortal = $variant === 'portal';
    $isGrouped = (bool) ($grouped ?? false);
    $lineType = (string) ($line['type'] ?? '');
    $typeLabel = trim((string) ($line['type_label'] ?? ''));
    $description = trim((string) ($line['customer_part_description'] ?? $line['description'] ?? ''));
    $suppressDuplicate = (bool) ($line['suppress_duplicate_description'] ?? false)
        || (
            $lineType === 'labor'
            && $isGrouped
            && \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::matchesParent(
                $description,
                $workGroupTitle ?? null,
            )
        );
    if ($suppressDuplicate) {
        $description = '';
    }
    $showTypeBadge = in_array($lineType, ['labor', 'sublet', 'part'], true);
    $groupedBadgeClass = match ($lineType) {
        'part' => 'line-type-badge--part',
        'sublet', 'labor' => 'line-type-badge--labor',
        default => '',
    };
    $portalBadgeClass = match ($lineType) {
        'part' => 'bg-sky-50 text-sky-800',
        'sublet', 'labor' => 'bg-emerald-50 text-emerald-800',
        default => 'bg-slate-100 text-slate-800',
    };
@endphp

@if ($isPortal)
    <div class="min-w-0">
        @if ($showTypeBadge)
            <p class="font-medium text-slate-800">
                <span class="mr-1.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $portalBadgeClass }}">{{ $typeLabel !== '' ? $typeLabel : ucfirst($lineType) }}</span>
                @if ($description !== '')
                    {{ $description }}
                @elseif ($suppressDuplicate && filled($line['quantity'] ?? null))
                    {{ $line['quantity'] }} hr
                @endif
            </p>
        @elseif ($lineType === 'note')
            <x-operations.note-body :text="$description" class="ops-note-body--portal" />
        @else
            <p class="font-medium text-slate-800">{{ $description }}</p>
        @endif
        @if ($lineType === 'part' && (filled($line['customer_part_number'] ?? null) || filled($line['customer_part_vendor'] ?? null) || filled($line['customer_part_supplier_sku'] ?? null)))
            <p class="mt-0.5 text-xs text-slate-500">
                @php
                    $procurementBits = array_values(array_filter([
                        filled($line['customer_part_number'] ?? null) ? (string) $line['customer_part_number'] : null,
                        filled($line['customer_part_vendor'] ?? null) ? (string) $line['customer_part_vendor'] : null,
                        filled($line['customer_part_supplier_sku'] ?? null) && ($line['customer_part_supplier_sku'] ?? null) !== ($line['customer_part_number'] ?? null)
                            ? 'SKU '.$line['customer_part_supplier_sku']
                            : null,
                    ]));
                @endphp
                {{ implode(' · ', $procurementBits) }}
            </p>
        @endif
    </div>
@else
    @if ($showTypeBadge)
        <span class="line-desc-row">
            <span class="line-type-badge {{ $groupedBadgeClass }}">{{ $typeLabel !== '' ? $typeLabel : ucfirst($lineType) }}</span>
            @if ($description !== '')
                <span class="line-desc-detail">{{ $description }}</span>
            @elseif ($suppressDuplicate && filled($line['quantity'] ?? null))
                <span class="line-desc-detail">{{ $line['quantity'] }} hr</span>
            @endif
        </span>
    @elseif ($lineType === 'note')
        <x-operations.note-body :text="$description" class="ops-note-body--pdf" />
    @else
        <span class="line-desc-primary">{{ $description }}</span>
    @endif
    @if ($lineType === 'part' && (filled($line['customer_part_number'] ?? null) || filled($line['customer_part_vendor'] ?? null) || filled($line['customer_part_supplier_sku'] ?? null)))
        <span class="line-desc-procurement">
            @php
                $procurementBits = array_values(array_filter([
                    filled($line['customer_part_number'] ?? null) ? (string) $line['customer_part_number'] : null,
                    filled($line['customer_part_vendor'] ?? null) ? (string) $line['customer_part_vendor'] : null,
                    filled($line['customer_part_supplier_sku'] ?? null) && ($line['customer_part_supplier_sku'] ?? null) !== ($line['customer_part_number'] ?? null)
                        ? 'SKU '.$line['customer_part_supplier_sku']
                        : null,
                ]));
            @endphp
            {{ implode(' · ', $procurementBits) }}
        </span>
    @endif
@endif
