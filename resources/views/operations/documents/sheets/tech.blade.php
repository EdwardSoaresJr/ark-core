<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RO #{{ $sheet['repair_order_id'] }} Technician Work Order</title>
    @include('operations.documents.sheets._tech-work-order-styles')
</head>
<body>
    <div class="tech-wo">
        @if (! empty($sheet['shop']['name']))
            <p class="tech-wo-shop">{{ $sheet['shop']['name'] }}</p>
        @endif

        <h1 class="tech-wo-doc-title">Technician Work Order</h1>

        <div class="tech-wo-meta">
            <p class="tech-wo-meta-line">RO #{{ $sheet['repair_order_id'] }}</p>
            <p class="tech-wo-meta-line">{{ $sheet['vehicle_label'] }}</p>
            <p class="tech-wo-meta-line tech-wo-meta-muted">VIN {{ $sheet['vin_display'] }} · Mileage {{ $sheet['mileage_display'] }}</p>
        </div>

        <div class="tech-wo-assignment">
            <div>
                <p class="tech-wo-field-label">Technician</p>
                <p class="tech-wo-field-value">{{ $sheet['technician_name'] }}</p>
            </div>
            <div>
                <p class="tech-wo-field-label">Work Station</p>
                <p class="tech-wo-field-value">{{ $sheet['work_station_label'] }}</p>
            </div>
        </div>

        <div class="tech-wo-flag-badge">
            <p class="tech-wo-flag-label">Approved Flag Hours</p>
            <p class="tech-wo-flag-value">{{ $sheet['approved_flag_hours'] }} HOURS</p>
            <p class="tech-wo-flag-hint">Hours assigned to approved work on this sheet — shop production record</p>
        </div>

        <p class="tech-wo-printed">Printed {{ $sheet['printed_at'] }}</p>

        @if (! $sheet['has_approved_work'])
            <p class="tech-wo-empty">No approved concerns yet. Print this work order after customer authorization and concern approval.</p>
        @else
            @foreach ($sheet['packages'] ?? $sheet['concerns'] as $package)
                <section class="tech-wo-concern">
                    <h2 class="tech-wo-concern-title">{{ $package['title'] ?? $package['summary'] }}</h2>
                    @if (! empty($package['owner_name']))
                        <p class="tech-wo-meta-muted" style="margin:0 0 4px;">Owner · {{ $package['owner_name'] }}</p>
                    @endif
                    @if (! empty($package['status_label']))
                        <p class="tech-wo-meta-muted" style="margin:0 0 4px;">Status · {{ $package['status_label'] }}</p>
                    @endif
                    @if (! empty($package['latest_update']))
                        <div class="tech-wo-section tech-wo-notes" style="margin-bottom:10px;">
                            <p class="tech-wo-section-label">Update</p>
                            <div class="tech-wo-note">
                                <x-operations.note-body :text="$package['latest_update']" />
                            </div>
                            @if (! empty($package['updated_at']))
                                <p class="tech-wo-meta-muted" style="margin:4px 0 0;">Updated {{ $package['updated_at'] }}</p>
                            @endif
                        </div>
                    @endif
                    @if (! empty($package['concern_summary']) && ($package['concern_summary'] ?? '') !== ($package['title'] ?? ''))
                        <p class="tech-wo-meta-muted" style="margin:0 0 8px;">{{ $package['concern_summary'] }}</p>
                    @endif

                    @if (! empty($package['labor']))
                        <div class="tech-wo-section">
                            <p class="tech-wo-section-label">Labor</p>
                            <ul class="tech-wo-checklist">
                                @foreach ($package['labor'] as $line)
                                    <li>
                                        <span class="tech-wo-check" aria-hidden="true"></span>
                                        <span class="tech-wo-check-text">
                                            @if (! empty($line['suppress_duplicate']) && ! empty($line['operation_title']))
                                                <span class="tech-wo-op">{{ $line['operation_title'] }}</span>
                                                <span class="tech-wo-hours">{{ $line['hours_only_label'] ?? $line['quantity'].' hrs' }}</span>
                                            @else
                                                {{ $line['label'] }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($package['sublets']))
                        <div class="tech-wo-section">
                            <p class="tech-wo-section-label">Sublet / Service</p>
                            <ul class="tech-wo-checklist">
                                @foreach ($package['sublets'] as $sublet)
                                    <li>
                                        <span class="tech-wo-check" aria-hidden="true"></span>
                                        <span class="tech-wo-check-text">{{ $sublet['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($package['parts']))
                        <div class="tech-wo-section">
                            <p class="tech-wo-section-label">Parts</p>
                            <ul class="tech-wo-checklist">
                                @foreach ($package['parts'] as $part)
                                    <li>
                                        <span class="tech-wo-check" aria-hidden="true"></span>
                                        <span class="tech-wo-check-text">{{ $part['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($package['work_notes']))
                        <div class="tech-wo-section tech-wo-notes">
                            <p class="tech-wo-section-label">Work Notes</p>
                            @foreach ($package['work_notes'] as $note)
                                <div class="tech-wo-note">
                                    <p class="tech-wo-note-label">{{ $note['label'] }}</p>
                                    <x-operations.note-body :text="$note['description']" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        @endif
    </div>
</body>
</html>
