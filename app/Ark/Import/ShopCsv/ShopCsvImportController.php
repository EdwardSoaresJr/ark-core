<?php

namespace App\Ark\Import\ShopCsv;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShopCsvImportController
{
    public function __construct(
        private readonly ShopCsvImporter $importer,
    ) {}

    public function template(): StreamedResponse
    {
        $csv = implode(',', [
            'first_name',
            'last_name',
            'phone',
            'email',
            'address_line_1',
            'city',
            'state',
            'postal_code',
            'customer_type',
            'notes',
            'year',
            'make',
            'model',
            'vin',
            'plate',
            'plate_state',
        ])."\n".
            'Maria,Lopez,(719) 555-0142,maria@example.com,12 Main St,Demo City,CO,80903,Retail,Prefers texts,2018,Honda,Civic,,ABC123,CO'."\n".
            'James,Nguyen,7195550199,james@example.com,,,,,Fleet,,2020,Ford,F-150,1FTEW1E50LFA00001,,'."\n";

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'ark-shop-customers-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $path = $this->storeUpload($request);
        $token = basename($path);

        $report = $this->importer->preview($path);

        $request->session()->put('shop_csv_import', [
            'token' => $token,
            'user_id' => $request->user()?->id,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('shop_csv_report', $report)
            ->with('shop_csv_mode', 'preview')
            ->with('shop_csv_token', $token);
    }

    public function commit(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        $path = $this->resolvePreviewPath($request, $token);

        if ($path === null) {
            $path = $this->storeUpload($request);
        }

        $report = $this->importer->commit($path);

        if ($token !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $token) === 1) {
            Storage::disk('local')->delete('shop-csv-imports/'.$token);
        }
        $request->session()->forget('shop_csv_import');

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('shop_csv_report', $report)
            ->with('shop_csv_mode', 'commit')
            ->with('status', $report['ok']
                ? "Import finished: {$report['create']} created, {$report['update']} updated, {$report['vehicles']} vehicles, {$report['skip']} skipped."
                : ($report['error'] ?? 'Import failed.'));
    }

    private function resolvePreviewPath(Request $request, string $token): ?string
    {
        if ($token === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $token) !== 1) {
            return null;
        }

        $preview = $request->session()->get('shop_csv_import');
        if (! is_array($preview)) {
            return null;
        }

        if (($preview['token'] ?? null) !== $token) {
            return null;
        }

        if ((int) ($preview['user_id'] ?? 0) !== (int) $request->user()?->id) {
            return null;
        }

        if ((int) ($preview['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('shop_csv_import');
            Storage::disk('local')->delete('shop-csv-imports/'.$token);

            return null;
        }

        $candidate = 'shop-csv-imports/'.$token;
        if (! Storage::disk('local')->exists($candidate)) {
            return null;
        }

        return Storage::disk('local')->path($candidate);
    }

    private function storeUpload(Request $request): string
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('csv');
        $stored = $file->storeAs(
            'shop-csv-imports',
            uniqid('import_', true).'.csv',
            'local',
        );

        if ($stored === false) {
            throw ValidationException::withMessages([
                'csv' => 'Could not store the uploaded CSV.',
            ]);
        }

        return Storage::disk('local')->path($stored);
    }
}
