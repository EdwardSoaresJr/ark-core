<x-operations.app title="Work Order - {{ $landing['ro_label'] }}" ui="tabler">
    @php
        /** @var array<string, mixed> $landing */
        $coverage = $inspectionCoverage ?? $landing['coverage'];
        $canRecord = (bool) ($coverage['can_record'] ?? false);
        $showInspection = $canRecord && ! $repairOrder->isTerminal();
        $captureUrl = $landing['capture_url'] ?? $coverage['capture_url'] ?? $landing['walk_url'];
        $walkUrl = $landing['walk_url'] ?? $coverage['walk_url'];
        $tabletUrl = $landing['tablet_url'] ?? $coverage['tablet_url'];
        $captureSurface = $landing['capture_surface'] ?? $coverage['capture_surface'] ?? 'desktop_walk';
    @endphp

    <x-tabler.page layout="focused" pretitle="Work order" :title="$landing['vehicle_label']" stack-actions>
        <x-slot:actions>
            <x-tabler.page-action :href="$landing['back_url']" icon="arrow-left" label="Job Board" />
        </x-slot:actions>

        <div class="d-flex flex-column gap-3" data-technician-production-landing>
            <div class="text-secondary">
                <span class="fw-bold text-reset">{{ $landing['ro_label'] }}</span>
                · {{ $landing['customer_label'] }}
                · {{ $landing['status_label'] }}
            </div>

            <div class="card" data-tech-why-here>
                <div class="card-body p-3">
                    <div class="subheader">Why it’s here</div>
                    <div class="fw-medium mt-1">{{ $landing['why_here'] }}</div>
                </div>
            </div>

            @if (count($landing['concerns']) > 0)
                <div class="card" data-tech-concerns>
                    <div class="card-header">
                        <h2 class="card-title">Owned Repair Actions</h2>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($landing['concerns'] as $concern)
                            <div class="list-group-item">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <span class="fw-medium">{{ $concern['summary'] }}</span>
                                    <span class="badge bg-secondary-lt flex-shrink-0">
                                        {{ $concern['status_label'] ?? $concern['disposition_label'] }}
                                    </span>
                                </div>
                                @if (! empty($concern['latest_update']))
                                    <div class="subheader mt-2">Update</div>
                                    <div style="white-space: pre-wrap">{{ $concern['latest_update'] }}</div>
                                    @if (! empty($concern['updated_at']))
                                        <div class="small text-secondary mt-1">Updated {{ $concern['updated_at'] }}</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($showInspection)
                <div class="card border-primary" data-inspection-entry>
                    <div class="card-body p-3">
                        <div class="subheader">Inspection</div>
                        @if (! empty($coverage['template_name']))
                            <div class="small text-secondary mt-1" data-inspection-template-name>{{ $coverage['template_name'] }}</div>
                        @endif
                        <div class="mt-1" data-inspection-coverage data-inspection-posture="{{ $coverage['posture_key'] ?? '' }}">
                            <div class="h3 mb-0">{{ $coverage['posture_headline'] ?? $coverage['posture_label'] }}</div>
                            @if (filled($coverage['posture_detail'] ?? null))
                                <div class="text-secondary fw-medium">{{ $coverage['posture_detail'] }}</div>
                            @endif
                        </div>
                        <p class="text-secondary mt-1 mb-3">{{ $landing['next_action'] }}</p>
                        <a
                            href="{{ $captureUrl }}"
                            class="btn btn-primary btn-lg w-100"
                            data-inspection-cta
                            data-inspection-capture-cta
                            data-capture-surface="{{ $captureSurface }}"
                            data-desktop-walk-url="{{ $walkUrl }}"
                            data-tablet-url="{{ $tabletUrl }}"
                        >{{ $coverage['cta_label'] }}</a>
                        <details class="mt-3">
                            <summary class="text-secondary fw-medium py-2">Other layout</summary>
                            <div class="btn-list mt-1">
                                <a href="{{ $tabletUrl }}" class="btn" data-inspection-tablet>Bay layout</a>
                                <a href="{{ $walkUrl }}" class="btn" data-inspection-desk>Desk layout</a>
                            </div>
                        </details>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary mb-0" role="status">
                    Inspection recording isn’t available on this repair order for your account.
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
