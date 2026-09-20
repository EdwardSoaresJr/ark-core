<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\DealerQuote;
use App\Ark\Operations\Parts\DealerQuoteOcr;
use App\Ark\Operations\Parts\DealerQuoteParser;
use App\Ark\Operations\Parts\DealerQuoteTextExtractor;
use App\Ark\Operations\RepairOrders\PartLineClassification;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function dealerQuoteSampleText(): string
{
    return <<<'TEXT'
Bob Penkhus Volkswagen
Invoice #: Q19696
Vehicle: 2013 Tiguan
VIN: WVGAV7AX0DW555555

Qty Part Number Description Cost
1 06J-103-603-BD Upper Oil Sump 406.08
12 N-910-488-02 Screw 6.53
2 N-910-506-01 Torx Screw 2.75

Dealer Total $1,147.42
TEXT;
}

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function dealerQuoteCaptureFixture(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Quote',
        'last_name' => 'Customer',
        'phone' => '555-0196',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'QTE196',
        'year' => 2013,
        'make' => 'Volkswagen',
        'model' => 'Tiguan',
        'vin' => 'WVGAV7AX0DW555555',
        'normalized_vin' => 'WVGAV7AX0DW555555',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil leak',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Oil leak',
        'position' => 1,
    ]);

    return [$repairOrder, $concern];
}

it('parses penkhus-style dealer quote text', function () {
    $parsed = app(DealerQuoteParser::class)->parse(dealerQuoteSampleText());

    expect($parsed['supplier_name'])->toContain('Penkhus')
        ->and($parsed['quote_number'])->toBe('Q19696')
        ->and($parsed['vin'])->toBe('WVGAV7AX0DW555555')
        ->and($parsed['lines'])->toHaveCount(3)
        ->and($parsed['lines'][0]['part_number'])->toBe('06J-103-603-BD')
        ->and($parsed['lines'][0]['part_cost'])->toBe('406.08')
        ->and($parsed['lines'][1]['quantity'])->toBe('12');
});

// Real third-party dealer OCR fixture excluded from public staging.
it('parses real penkhus cdk ocr fixture for Q19696', function () {
    $fixture = base_path('tests/Fixtures/dealer-quotes/penkhus-q19696-ocr.txt');
    if (! is_file($fixture)) {
        $this->markTestSkipped('Private dealer OCR fixture not shipped in public staging.');
    }

    $ocr = file_get_contents($fixture);
    $parsed = app(DealerQuoteParser::class)->parse((string) $ocr);

    expect($parsed['supplier_name'])->toBe('Bob Penkhus Volkswagen')
        ->and($parsed['quote_number'])->toBe('Q19696')
        ->and($parsed['vin'])->toBe('WVGBV3AX1DW591493')
        ->and($parsed['lines'])->toHaveCount(13)
        ->and($parsed['dealer_total_cents'])->toBe(114742)
        ->and($parsed['lines'][0]['part_number'])->toBe('06J-103-603-BD')
        ->and($parsed['lines'][0]['quantity'])->toBe('1')
        ->and($parsed['lines'][0]['part_cost'])->toBe('406.08')
        ->and($parsed['lines'][1]['part_number'])->toBe('N-910-488-02')
        ->and($parsed['lines'][1]['quantity'])->toBe('12');
});

it('analyzes pasted dealer quote text and returns review payload', function () {
    [$repairOrder] = dealerQuoteCaptureFixture();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_text' => dealerQuoteSampleText(),
        ])
        ->assertOk()
        ->assertJsonPath('quote_number', 'Q19696')
        ->assertJsonPath('lines.0.part_number', '06J-103-603-BD')
        ->assertJsonCount(3, 'lines');
});

it('captures selected dealer quote lines onto the estimate with provenance', function () {
    Storage::fake('local');

    [$repairOrder, $concern] = dealerQuoteCaptureFixture();
    $user = actingAsLearnCurrentAdvisor();

    $analyze = $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_text' => dealerQuoteSampleText(),
        ])
        ->assertOk()
        ->json();

    $assignments = collect($analyze['lines'])->map(fn (array $line): array => [
        'source_key' => $line['source_key'],
        'repair_order_concern_id' => $concern->id,
        'part_cost' => $line['part_cost'],
    ])->all();

    $this->actingAs($user)
        ->post(route('operations.repair-orders.dealer-quotes.store', $repairOrder), [
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
            'capture' => [
                'supplier_name' => $analyze['supplier_name'],
                'quote_number' => $analyze['quote_number'],
                'vehicle_description' => $analyze['vehicle_description'],
                'vin' => $analyze['vin'],
                'dealer_total_cents' => $analyze['dealer_total_cents'],
                'raw_text' => $analyze['raw_text'],
                'original_filename' => null,
                'temp_storage_path' => null,
                'lines' => $analyze['lines'],
            ],
            'assignments' => $assignments,
        ])
        ->assertRedirect();

    $quote = DealerQuote::query()->where('repair_order_id', $repairOrder->id)->first();
    expect($quote)->not->toBeNull()
        ->and($quote->quote_number)->toBe('Q19696')
        ->and($quote->captured_by_user_id)->toBe($user->id)
        ->and($quote->lines)->toHaveCount(3);

    $partLines = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Part->value)
        ->get();

    expect($partLines)->toHaveCount(3)
        ->and($partLines->every(fn (RepairOrderLine $line): bool => $line->dealer_quote_line_id !== null))->toBeTrue()
        ->and($partLines->first()->part_classification)->toBe(PartLineClassification::Oem)
        ->and($partLines->first()->vendor_name)->toContain('Penkhus');

    $this->actingAs($user)
        ->get(route('operations.repair-orders.dealer-quotes.show', [$repairOrder, $quote]))
        ->assertOk()
        ->assertSee('Q19696')
        ->assertSee('06J-103-603-BD');
});

it('captures dealer quote lines with an explicit parts matrix', function () {
    Storage::fake('local');

    [$repairOrder, $concern] = dealerQuoteCaptureFixture();
    $user = actingAsLearnCurrentAdvisor();

    $analyze = $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_text' => dealerQuoteSampleText(),
        ])
        ->assertOk()
        ->json();

    $line = $analyze['lines'][0];

    $this->actingAs($user)
        ->post(route('operations.repair-orders.dealer-quotes.store', $repairOrder), [
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
            'capture' => [
                'supplier_name' => $analyze['supplier_name'],
                'quote_number' => $analyze['quote_number'],
                'vehicle_description' => $analyze['vehicle_description'],
                'vin' => $analyze['vin'],
                'dealer_total_cents' => $analyze['dealer_total_cents'],
                'raw_text' => $analyze['raw_text'],
                'original_filename' => null,
                'temp_storage_path' => null,
                'lines' => $analyze['lines'],
            ],
            'assignments' => [
                [
                    'source_key' => $line['source_key'],
                    'repair_order_concern_id' => $concern->id,
                    'part_cost' => $line['part_cost'],
                    'pricing_matrix_key' => 'oem-parts',
                ],
            ],
        ])
        ->assertRedirect();

    $partLine = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Part->value)
        ->sole();

    expect($partLine->pricing_mode)->toBe('matrix')
        ->and($partLine->pricing_matrix_key)->toBe('oem-parts')
        ->and($partLine->matrix_applied)->toBeTrue();
});

it('captures dealer quote lines with a renamed part description', function () {
    Storage::fake('local');

    [$repairOrder, $concern] = dealerQuoteCaptureFixture();
    $user = actingAsLearnCurrentAdvisor();

    $analyze = $this->actingAs($user)
        ->postJson(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_text' => dealerQuoteSampleText(),
        ])
        ->assertOk()
        ->json();

    $line = $analyze['lines'][0];

    $this->actingAs($user)
        ->post(route('operations.repair-orders.dealer-quotes.store', $repairOrder), [
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
            'capture' => [
                'supplier_name' => $analyze['supplier_name'],
                'quote_number' => $analyze['quote_number'],
                'vehicle_description' => $analyze['vehicle_description'],
                'vin' => $analyze['vin'],
                'dealer_total_cents' => $analyze['dealer_total_cents'],
                'raw_text' => $analyze['raw_text'],
                'original_filename' => null,
                'temp_storage_path' => null,
                'lines' => $analyze['lines'],
            ],
            'assignments' => [
                [
                    'source_key' => $line['source_key'],
                    'repair_order_concern_id' => $concern->id,
                    'part_cost' => $line['part_cost'],
                    'description' => 'Upper oil pan gasket kit',
                ],
            ],
        ])
        ->assertRedirect();

    $partLine = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Part->value)
        ->sole();

    expect($partLine->description)->toBe('Upper oil pan gasket kit');
});

it('analyzes an uploaded text quote file', function () {
    [$repairOrder] = dealerQuoteCaptureFixture();

    $file = UploadedFile::fake()->createWithContent('penkhus.txt', dealerQuoteSampleText());

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_pdf' => $file,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('quote_number', 'Q19696')
        ->assertJsonCount(3, 'lines');
});

it('ocrs a quote photo when tesseract is available', function () {
    if (! app(DealerQuoteOcr::class)->available()) {
        $this->markTestSkipped('tesseract is not installed');
    }

    [$repairOrder] = dealerQuoteCaptureFixture();

    $path = sys_get_temp_dir().'/dealer-quote-ocr-'.uniqid().'.png';
    $image = imagecreatetruecolor(1400, 500);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 1400, 500, $white);
    imagestring($image, 5, 40, 40, 'Bob Penkhus Volkswagen', $black);
    imagestring($image, 5, 40, 80, 'Invoice #: Q19696', $black);
    imagestring($image, 5, 40, 140, '1 06J-103-603-BD Upper Oil Sump 406.08', $black);
    imagestring($image, 5, 40, 180, '12 N-910-488-02 Screw 6.53', $black);
    imagestring($image, 5, 40, 220, '2 N-910-506-01 Torx Screw 2.75', $black);
    imagepng($image, $path);
    imagedestroy($image);

    $file = new UploadedFile($path, 'penkhus-scan.png', 'image/png', null, true);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.repair-orders.dealer-quotes.analyze', $repairOrder), [
            'quote_pdf' => $file,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->whereType('quote_number', ['string', 'null'])
            ->has('lines', 3)
            ->etc()
        );

    @unlink($path);
});

it('falls back to ocr when a pdf has no extractable text', function () {
    $ocr = app(DealerQuoteOcr::class);

    if (! $ocr->available() || (! is_executable('/opt/homebrew/bin/pdftoppm') && ! is_executable('/usr/bin/pdftoppm'))) {
        $this->markTestSkipped('tesseract/pdftoppm not installed');
    }

    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('imagick required to synthesize a scanned pdf fixture');
    }

    $png = sys_get_temp_dir().'/dealer-quote-scan-'.uniqid().'.png';
    $pdf = sys_get_temp_dir().'/dealer-quote-scan-'.uniqid().'.pdf';

    $image = imagecreatetruecolor(1400, 500);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 1400, 500, $white);
    imagestring($image, 5, 40, 40, 'Bob Penkhus Volkswagen Invoice #: Q19696', $black);
    imagestring($image, 5, 40, 120, '1 06J-103-603-BD Upper Oil Sump 406.08', $black);
    imagestring($image, 5, 40, 160, '12 N-910-488-02 Screw 6.53', $black);
    imagepng($image, $png);
    imagedestroy($image);

    $imagick = new Imagick;
    $imagick->readImage($png);
    $imagick->setImageFormat('pdf');
    $imagick->writeImage($pdf);
    $imagick->clear();
    $imagick->destroy();

    $text = app(DealerQuoteTextExtractor::class)->fromPdfPath($pdf);
    $normalized = str_replace(['—', '–', '−'], '-', $text);

    expect($normalized)->toContain('Q19696')
        ->and($normalized)->toMatch('/06J-+103-603-BD/i');

    @unlink($png);
    @unlink($pdf);
});
