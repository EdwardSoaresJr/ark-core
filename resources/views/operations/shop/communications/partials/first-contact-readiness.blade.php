@php
    /** @var array{checks: list<array{label: string, passed: bool}>, ready: bool, action_label: string, action_url: ?string} $firstContact */
@endphp

<section id="first-contact" class="card" aria-labelledby="first-contact-title">
    <div class="card-header">
        <div>
            <h2 class="card-title" id="first-contact-title">First Contact</h2>
            <div class="card-subtitle">Operator path from new device to Connected (bench gates G1–G7 for admins).</div>
        </div>
        <div class="card-actions">
            @if ($firstContact['action_url'])
                <a href="{{ $firstContact['action_url'] }}" class="btn btn-primary btn-sm">
                    {{ $firstContact['action_label'] }}
                </a>
            @else
                <span class="badge bg-yellow-lt">{{ $firstContact['action_label'] }}</span>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Check</th>
                    <th class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($firstContact['checks'] as $check)
                    <tr>
                        <td>{{ $check['label'] }}</td>
                        <td @class(['text-end fw-bold', 'text-success' => $check['passed'], 'text-danger' => ! $check['passed']])>
                            {{ $check['passed'] ? '✓' : '-' }}
                            <span class="visually-hidden">{{ $check['passed'] ? 'Passed' : 'Not passed' }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
