@php
    $report = session('shop_csv_report');
    $mode = session('shop_csv_mode');
    $token = session('shop_csv_token');
@endphp

<div data-settings-shop-import>
    <h3 class="mb-1">Shop import</h3>
    <p class="text-secondary small">
        Bring customers (and vehicles) from a spreadsheet so the shop feels familiar on day one.
        Creates customer and vehicle records only. Repair history is not imported.
    </p>

    <p class="small text-secondary">
        <a href="{{ route('operations.settings.shop.import.template') }}">Download CSV template</a>
        · Match by phone, then email · Max {{ \App\Ark\Import\ShopCsv\ShopCsvImporter::MAX_ROWS }} rows
    </p>

    <form method="POST" action="{{ route('operations.settings.shop.import.preview') }}" enctype="multipart/form-data" class="row g-2 align-items-end" style="max-width: 36rem">
        @csrf
        <div class="col-12 col-sm">
            <label for="shop-csv-file" class="form-label required">CSV file</label>
            <input id="shop-csv-file" type="file" name="csv" accept=".csv,text/csv" required @class(['form-control', 'is-invalid' => $errors->has('csv')])>
            @error('csv')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12 col-sm-auto">
            <button type="submit" class="btn">
                Preview
            </button>
        </div>
    </form>

    @if (is_array($report))
        <div class="card card-sm mt-3" data-shop-import-report>
            <div class="card-body">
                @if (! empty($report['error']))
                    <div class="alert alert-danger mb-0" role="alert">{{ $report['error'] }}</div>
                @else
                    <p class="fw-medium mb-2">
                        {{ $mode === 'commit' ? 'Imported' : 'Preview' }}:
                        {{ $report['create'] }} create ·
                        {{ $report['update'] }} update ·
                        {{ $report['vehicles'] }} vehicles ·
                        {{ $report['skip'] }} skip
                        <span class="fw-normal text-secondary">({{ $report['row_count'] }} rows)</span>
                    </p>

                    @if (! empty($report['mapped']))
                        <p class="small text-secondary mb-2">
                            Mapped:
                            @foreach ($report['mapped'] as $field => $header)
                                <span class="badge bg-secondary-lt font-monospace me-1">{{ $field }}←{{ $header }}</span>
                            @endforeach
                        </p>
                    @endif

                    @if (! empty($report['warnings']))
                        <div class="alert alert-warning small" role="status">
                            <ul class="mb-0 ps-3" style="list-style: disc">
                                @foreach ($report['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($report['sample']))
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-vcenter card-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Action</th>
                                        <th>Customer</th>
                                        <th>Vehicle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['sample'] as $row)
                                        <tr>
                                            <td class="text-secondary font-monospace">{{ $row['row'] }}</td>
                                            <td class="fw-medium">{{ $row['action'] }}</td>
                                            <td>{{ $row['customer'] }}</td>
                                            <td class="text-secondary">{{ $row['vehicle'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($mode === 'preview' && $report['ok'] && filled($token))
                        <form method="POST" action="{{ route('operations.settings.shop.import.commit') }}" class="d-flex justify-content-end mt-3">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="btn btn-primary">
                                Import {{ $report['create'] + $report['update'] }} customers
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
