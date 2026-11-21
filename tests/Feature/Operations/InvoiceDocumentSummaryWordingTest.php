<?php

use App\Ark\Operations\Documents\DocumentFooterPresenter;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Settings\ShopSettings;
test('issued invoice snapshots and pdf use invoice summary wording, not estimate authorization', function () {
    ShopSettings::current()->update([
        'authorization_language' => ShopSettings::DEFAULT_AUTHORIZATION_LANGUAGE,
    ]);
    ShopSettings::forgetCurrent();

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $snapshotJson = json_encode($invoice->snapshot_json ?? []);
    $docsAuth = (string) data_get($invoice->snapshot_json, 'documents.authorization_language');
    $settingsAuth = (string) data_get($invoice->snapshot_json, 'settings.authorization_language');

    expect($docsAuth)
        ->toContain('This invoice reflects')
        ->not->toContain('By approving this estimate')
        ->and($settingsAuth)
        ->toBe(ShopSettings::DEFAULT_INVOICE_SUMMARY_LANGUAGE)
        ->and(str_contains((string) $snapshotJson, 'By approving this estimate'))->toBeFalse();

    $snapshot = app(EstimateDocumentPdfSnapshot::class)->resolve($invoice);
    $snapshot['document_footer'] = app(DocumentFooterPresenter::class)->present($snapshot);

    $html = view('operations.documents.pdf.document', [
        'document' => $invoice,
        'snapshot' => $snapshot,
    ])->render();
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));

    expect($text)
        ->toContain('This invoice reflects')
        ->not->toContain('By approving this estimate');
});

test('estimate documents keep estimate authorization wording', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh());

    $authorization = (string) data_get($snapshot, 'documents.authorization_language');

    expect($authorization)->toContain('By approving this estimate');
});

test('historical invoice snapshots keep frozen summary text when shop settings change', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $invoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder->fresh());
    $frozenSummary = (string) data_get($invoice->snapshot_json, 'documents.authorization_language');

    ShopSettings::current()->update([
        'authorization_language' => 'Revised estimate authorization after invoice issue.',
    ]);
    ShopSettings::forgetCurrent();

    $presented = app(DocumentPdfPresenter::class)->prepare($invoice->snapshot_json ?? []);

    expect($presented['documents']['authorization_language'])->toBe($frozenSummary)
        ->and($frozenSummary)->toContain('This invoice reflects');
});

test('document footer presents invoice summary for legacy invoice snapshots that froze estimate authorization', function () {
    $legacyEstimateAuth = 'By approving this estimate, I authorize Example Shop to perform repairs.';

    $footer = app(DocumentFooterPresenter::class)->present([
        'document_type' => 'invoice',
        'documents' => [
            'authorization_language' => $legacyEstimateAuth,
        ],
        'settings' => [
            'authorization_language' => ShopSettings::DEFAULT_AUTHORIZATION_LANGUAGE,
        ],
        'concerns' => [['disposition' => 'approved']],
    ]);

    $authorizationText = implode(' ', $footer['authorization']);

    // Snapshot JSON remains estimate-flavored; customer-facing footer does not.
    expect($authorizationText)
        ->toContain('This invoice reflects')
        ->not->toContain('By approving this estimate')
        ->and($legacyEstimateAuth)->toContain('By approving this estimate');
});