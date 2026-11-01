<?php

use App\Ark\Install\InstallationState;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\HeadlessChromiumPdfRenderer;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderAuthorizationStoreController;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\DisposableMySqlIntegration;
use Tests\Support\TestEnvironment;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$mysql = DisposableMySqlIntegration::helperConfiguration();
TestEnvironment::initialize($mysql);
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
TestEnvironment::configureApplication($app, $mysql);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config(['broadcasting.default' => 'log']);

if ($kernel->call('migrate', ['--force' => true, '--quiet' => true]) !== 0) {
    throw new RuntimeException('Isolated MySQL migrations failed.');
}
$kernel->call('db:seed', ['--class' => ArkAuthorizationSeeder::class, '--force' => true]);
$kernel->call('db:seed', ['--class' => RepairOrderStatusCatalogSeeder::class, '--force' => true]);
InstallationState::markInstalled();
ShopDisplayTimezone::apply();

/** Renders the real PDF document view, as Chromium would receive it, and keeps the HTML. */
final class CapturingPdfRenderer implements PdfRenderer
{
    /** @var list<array{type: string, html: string}> */
    public static array $renders = [];

    public function renderEstimate(EstimateDocument $document): string
    {
        $html = view(HeadlessChromiumPdfRenderer::VIEW, [
            'document' => $document,
            'snapshot' => app(EstimateDocumentPdfSnapshot::class)->resolve($document),
        ])->render();
        self::$renders[] = ['type' => $document->isInvoice() ? 'invoice' : 'estimate', 'html' => $html];

        $path = DocumentPdfPath::for($document);
        Storage::disk('local')->put($path, "%PDF-1.4\n%render ".count(self::$renders)."\n");
        $document->forceFill([
            'pdf_path' => $path,
            'generated_at' => $document->generated_at ?? now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
            'status' => $document->isInvoice() ? $document->status : 'generated',
        ])->save();

        return $path;
    }
}
$app->instance(PdfRenderer::class, new CapturingPdfRenderer);

ShopSettings::current()->update([
    'shop_fee_enabled' => false,
    'tax_enabled' => false,
    'default_deposit_enabled' => false,
    'portal_signature_required' => false,
]);

$staff = User::factory()->create()->assignRole(ArkRole::Admin->value, ArkRole::Advisor->value);
$app['auth']->guard('web')->setUser($staff);
$http = $app->make(HttpKernel::class);
$send = static function (string $method, string $uri, array $data = []) use ($http, $app): array {
    $app['session.store']->forget('errors');
    $response = $http->handle(Request::create($uri, $method, $data));
    $errors = $app['session.store']->get('errors');

    return [
        'status' => $response->getStatusCode(),
        'errors' => match (true) {
            is_object($errors) => $errors->getBag('default')->all(),
            is_array($errors) => array_values(array_filter(Arr::flatten($errors), fn ($m): bool => $m !== ':message')),
            default => [],
        },
        'body' => (string) $response->getContent(),
    ];
};
$text = static fn (string $html): string => trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $html))));
$money = static fn (int $cents): string => '$'.number_format($cents / 100, 2);
$lastInvoiceRender = static fn (array $renders): string => $text((string) (collect($renders)->where('type', 'invoice')->last()['html'] ?? ''));

$customer = Customer::query()->create(['first_name' => 'Ceiling', 'last_name' => 'Customer', 'phone' => '5550144001']);
$vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2019, 'make' => 'Toyota', 'model' => 'Camry', 'vin' => '4T1B11HK5KU123456']);
$technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
$repairOrder = RepairOrder::query()->create([
    'customer_id' => $customer->id,
    'vehicle_id' => $vehicle->id,
    'assigned_technician_id' => $technician->id,
    'status' => RepairOrderStatus::WaitingApproval,
    'mileage_in' => 10000,
    'mileage_out' => 10005,
    'concern_summary' => 'Front brakes squeal',
]);
// A Check-In customer concern: diagnostic intent, as Check-In records it.
$concern = RepairOrderConcern::query()->create([
    'repair_order_id' => $repairOrder->id,
    'summary' => 'Front brakes',
    'disposition' => RepairOrderConcernDisposition::Recommended,
    'recommendation_intent' => 'diagnostic',
    'position' => 1,
]);
foreach ([
    [RepairOrderLineType::Labor, 'Front brake pad replacement', '100.00'],
    [RepairOrderLineType::Part, 'Front brake pads', '50.00'],
] as [$type, $description, $price]) {
    $send('POST', route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id, 'type' => $type->value,
        'description' => $description, 'quantity' => '1.00', 'unit_price' => $price,
    ]);
}
$labor = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->where('type', RepairOrderLineType::Labor)->sole();

// The record form as staff submit it, as rendered when it was opened (now, unless given).
$openForm = static fn (): array => [
    'version' => (int) $repairOrder->fresh()->estimate_version,
    'changes' => app(WorkCompletionAuthorization::class)->pendingChangesFingerprint($repairOrder->fresh(['concerns', 'lines.concern'])),
];
$approve = static fn (?string $amount = null, ?array $opened = null): array => $send('POST', route('operations.repair-orders.authorization.store', $repairOrder), array_filter([
    'source' => ApprovalSource::InPerson->value, 'approved_by' => 'Ceiling Customer', 'approved_amount' => $amount,
    RepairOrderConcurrency::FIELD => ($opened ?? $openForm())['version'],
    RepairOrderAuthorizationStoreController::PRESENTED_CHANGES_FIELD => ($opened ?? $openForm())['changes'],
], fn ($value): bool => $value !== null));

$first = $approve();
$approvedFirst = (int) ApprovalEvent::query()->where('visit_id', $repairOrder->id)->value('approved_amount_cents');

// A form opened before the increase, showing the earlier approved total.
$staleForm = $openForm();

// Price increase on the approved labor: half an hour more.
$labor = $labor->fresh();
$send('PATCH', route('operations.repair-orders.lines.update', [$repairOrder, $labor]), [
    'repair_order_concern_id' => $concern->id, 'type' => RepairOrderLineType::Labor->value,
    'description' => $labor->description, 'quantity' => '1.50', 'unit_price' => number_format($labor->unit_price_cents / 100, 2, '.', ''),
]);
$pendingForecast = app(ApprovalForecastProjection::class)->for($repairOrder->fresh(['concerns.lines', 'lines.concern']));
$estimate = app(EstimateDocumentService::class)->createOrRefresh($repairOrder->fresh());
$pendingEstimatePdf = $text(view(HeadlessChromiumPdfRenderer::VIEW, [
    'document' => $estimate, 'snapshot' => app(EstimateDocumentPdfSnapshot::class)->resolve($estimate),
])->render());
$afterIncrease = $pendingForecast['approved_cents'] + $pendingForecast['needs_authorization_cents'];

$contradicting = $approve(number_format(($afterIncrease - 2500) / 100, 2, '.', ''));
$eventsAfterContradicting = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->count();
$staleForm = $approve(number_format($approvedFirst / 100, 2, '.', ''), $staleForm);
$eventsAfterStaleForm = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->count();
$supplemental = $approve(number_format($afterIncrease / 100, 2, '.', ''));

$history = $text($send('GET', route('operations.repair-orders.workspace-tabs.show', [$repairOrder, 'auth']))['body']);

$deposit = $send('PATCH', route('operations.repair-orders.deposit.update', $repairOrder), [
    'amount' => '50.00', 'payment_method' => 'cash', 'deposit_confirmed' => '1',
    FinancialSubmissionIntentGate::FIELD => (string) Str::uuid(),
]);

$moves = [];
foreach (['approved', 'in_progress', 'ready_pickup'] as $status) {
    if ($repairOrder->fresh()->status->value === $status) {
        continue;
    }
    $moves[$status] = $send('PATCH', route('operations.repair-orders.lifecycle.update', $repairOrder), ['status' => $status])['errors'];
}
$invoice = EstimateDocument::query()->where('repair_order_id', $repairOrder->id)->where('document_type', FinancialDocumentType::Invoice->value)->first();
$snapshotAtIssue = $invoice?->snapshot_json;
$invoiceTotal = (int) data_get($snapshotAtIssue, 'totals.total_cents');
$rendersAtIssue = count(CapturingPdfRenderer::$renders);
$invoiceHtmlAtIssue = $lastInvoiceRender(CapturingPdfRenderer::$renders);

$payment = $send('PATCH', route('operations.repair-orders.payment.update', $repairOrder), [
    'amount' => number_format(($invoiceTotal - 5000) / 100, 2, '.', ''), 'payment_method' => 'cash',
    FinancialSubmissionIntentGate::FIELD => (string) Str::uuid(),
]);
$flaggedAfterPayment = (bool) $invoice?->fresh()->needs_pdf_refresh;
$close = $send('PATCH', route('operations.repair-orders.lifecycle.update', $repairOrder), ['status' => 'closed:paid']);
$invoice = $invoice?->fresh();
$closeRenders = array_slice(CapturingPdfRenderer::$renders, $rendersAtIssue);
$closeInvoiceHtml = $lastInvoiceRender($closeRenders);
$servedAfterClose = $invoice !== null
    ? $send('GET', route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $invoice]))
    : ['status' => null];

$events = ApprovalEvent::query()->where('visit_id', $repairOrder->id)->orderBy('id')->get();
$scopes = ApprovedWorkScope::query()->where('repair_order_concern_id', $concern->id)->orderBy('id')->get();

echo json_encode([
    'first_errors' => $first['errors'],
    'approved_first_cents' => $approvedFirst,
    'pending' => [
        'approved_cents' => $pendingForecast['approved_cents'],
        'needs_authorization_cents' => $pendingForecast['needs_authorization_cents'],
        'projected_cents' => $pendingForecast['projected_cents'],
        'estimate_pdf_says_approved_total' => str_contains($pendingEstimatePdf, 'Approved Total'),
        'estimate_pdf_shows_changes' => str_contains($pendingEstimatePdf, 'Changes Awaiting Your Approval '.$money($pendingForecast['needs_authorization_cents'])),
        'estimate_pdf_shows_approved' => str_contains($pendingEstimatePdf, 'Approved Work '.$money($pendingForecast['approved_cents'])),
    ],
    'after_increase_cents' => $afterIncrease,
    'contradicting' => ['errors' => $contradicting['errors'], 'expected_message_total' => $money($afterIncrease), 'events_after' => $eventsAfterContradicting],
    'stale_form' => ['status' => $staleForm['status'], 'events_after' => $eventsAfterStaleForm],
    'supplemental_errors' => $supplemental['errors'],
    'events' => $events->map(fn (ApprovalEvent $event): array => [
        'type' => $event->approval_type->value, 'amount_cents' => $event->approved_amount_cents,
    ])->all(),
    'scope_labor_subtotals' => $scopes->map(fn (ApprovedWorkScope $scope): int => (int) ($scope->approvedLines()[$labor->id]['subtotal_cents'] ?? 0))->all(),
    'labor_subtotal_now' => (int) $labor->fresh()->subtotal_cents,
    'history' => [
        'shows_first' => str_contains($history, $money($approvedFirst)),
        'shows_supplemental' => str_contains($history, $money($afterIncrease)),
        'says_deferred' => str_contains($history, 'Customer deferred recommended work'),
    ],
    'deposit_errors' => $deposit['errors'],
    'moves' => $moves,
    'invoice' => [
        'issued' => $invoice !== null,
        'total_cents' => $invoiceTotal,
        'at_issue_shows_balance' => str_contains($invoiceHtmlAtIssue, 'Balance due '.$money($invoiceTotal - 5000)),
        'payment_errors' => $payment['errors'],
        'flagged_after_payment' => $flaggedAfterPayment,
        'close_errors' => $close['errors'],
        'status' => $invoice?->status,
        'repair_order_status' => $repairOrder->fresh()->status->value,
        'invoice_renders_at_close' => collect($closeRenders)->where('type', 'invoice')->count(),
        'close_render_paid_in_full' => str_contains($closeInvoiceHtml, 'Paid in Full'),
        'close_render_balance_zero' => str_contains($closeInvoiceHtml, 'Balance due $0.00'),
        'needs_pdf_refresh' => (bool) $invoice?->needs_pdf_refresh,
        'snapshot_unchanged' => $invoice?->snapshot_json == $snapshotAtIssue,
        'served_status' => $servedAfterClose['status'],
        'invoice_renders_after_serving' => collect(array_slice(CapturingPdfRenderer::$renders, $rendersAtIssue))->where('type', 'invoice')->count(),
    ],
    'ledger' => RepairOrderLedgerEntry::query()->where('repair_order_id', $repairOrder->id)->orderBy('id')->get()
        ->map(fn (RepairOrderLedgerEntry $entry): array => [$entry->entry_type->value, $entry->amount_cents])->all(),
], JSON_THROW_ON_ERROR);
