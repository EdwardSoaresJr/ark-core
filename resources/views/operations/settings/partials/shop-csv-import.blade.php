@php
    $report = session('shop_csv_report');
    $mode = session('shop_csv_mode');
    $token = session('shop_csv_token');
@endphp

<div class="border-t border-slate-200 pt-3 mt-3">
    <div class="border-b border-slate-200 pb-3">
        <h4 class="text-xs font-black uppercase tracking-[0.08em] text-slate-700">Shop import</h4>
        <p class="mt-0.5 text-xs text-slate-500">
            Bring customers (and vehicles) from a spreadsheet so the shop feels familiar on day one.
            Writes Customer and Vehicle authority only — no staging tables, no repair history.
        </p>
    </div>

    <div class="mt-3 space-y-3 text-sm">
        <p class="text-xs text-slate-600">
            <a href="{{ route('operations.settings.shop.import.template') }}" class="font-semibold text-slate-800 underline decoration-slate-300 hover:text-slate-950">Download CSV template</a>
            · Match by phone, then email · Max {{ \App\Ark\Import\ShopCsv\ShopCsvImporter::MAX_ROWS }} rows
        </p>

        <form method="POST" action="{{ route('operations.settings.shop.import.preview') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
            @csrf
            <div class="min-w-[12rem] flex-1">
                <label for="shop-csv-file" class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">CSV file</label>
                <input id="shop-csv-file" type="file" name="csv" accept=".csv,text/csv" required class="mt-1 block w-full text-xs text-slate-700 file:mr-2 file:border file:border-slate-300 file:bg-white file:px-2 file:py-1 file:text-xs file:font-semibold file:text-slate-800">
            </div>
            <button type="submit" class="border border-slate-300 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-50">
                Preview
            </button>
        </form>

        @error('csv')
            <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
        @enderror

        @if (is_array($report))
            <div class="border border-slate-300 bg-slate-50 p-3 space-y-2">
                @if (! empty($report['error']))
                    <p class="text-xs font-semibold text-rose-800">{{ $report['error'] }}</p>
                @else
                    <p class="text-xs font-semibold text-slate-900">
                        {{ $mode === 'commit' ? 'Imported' : 'Preview' }}:
                        {{ $report['create'] }} create ·
                        {{ $report['update'] }} update ·
                        {{ $report['vehicles'] }} vehicles ·
                        {{ $report['skip'] }} skip
                        <span class="font-normal text-slate-500">({{ $report['row_count'] }} rows)</span>
                    </p>

                    @if (! empty($report['mapped']))
                        <p class="text-[11px] text-slate-600">
                            Mapped:
                            @foreach ($report['mapped'] as $field => $header)
                                <span class="mr-1 inline-block rounded bg-white px-1 py-0.5 font-mono text-[10px] text-slate-700">{{ $field }}←{{ $header }}</span>
                            @endforeach
                        </p>
                    @endif

                    @if (! empty($report['warnings']))
                        <ul class="list-disc space-y-0.5 pl-4 text-[11px] text-amber-900">
                            @foreach ($report['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($report['sample']))
                        <div class="overflow-x-auto border border-slate-200 bg-white">
                            <table class="min-w-full text-left text-[11px]">
                                <thead class="bg-slate-100 text-slate-600">
                                    <tr>
                                        <th class="px-2 py-1 font-bold">Row</th>
                                        <th class="px-2 py-1 font-bold">Action</th>
                                        <th class="px-2 py-1 font-bold">Customer</th>
                                        <th class="px-2 py-1 font-bold">Vehicle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['sample'] as $row)
                                        <tr class="border-t border-slate-100">
                                            <td class="px-2 py-1 tabular-nums text-slate-500">{{ $row['row'] }}</td>
                                            <td class="px-2 py-1 font-semibold text-slate-800">{{ $row['action'] }}</td>
                                            <td class="px-2 py-1 text-slate-800">{{ $row['customer'] }}</td>
                                            <td class="px-2 py-1 text-slate-600">{{ $row['vehicle'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($mode === 'preview' && $report['ok'] && filled($token))
                        <form method="POST" action="{{ route('operations.settings.shop.import.commit') }}" class="pt-1">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="bg-slate-950 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">
                                Import {{ $report['create'] + $report['update'] }} customers
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>
