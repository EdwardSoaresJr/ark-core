<?php

use App\Ark\Operations\Documents\AttachDocumentToRepairOrderAction;
use App\Ark\Operations\Documents\Document;
use App\Ark\Operations\Documents\DocumentEvent;
use App\Ark\Operations\Documents\DocumentEventType;
use App\Ark\Operations\Documents\DocumentSource;
use App\Ark\Operations\Documents\DocumentType;
use App\Ark\Operations\Documents\RetireDocumentAction;
use App\Ark\Operations\Documents\RotateDocumentAction;
use App\Ark\Operations\Documents\SetDocumentVisibilityAction;
use App\Ark\Operations\Documents\StoreDocumentAction;
use App\Ark\Operations\Documents\StoreScannedDocumentAction;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\DocumentCustomerMail;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
    Storage::fake('local');
});

function documentsFixture(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Dana',
        'last_name' => 'Walsh',
        'phone' => '555-0200',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'DOC123',
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Warranty paperwork',
    ])->fresh();

    return [$customer, $repairOrder];
}

test('upload pdf from repair order associates customer and ro once', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer, $repairOrder] = documentsFixture();

    $this->actingAs($admin)->post(
        route('operations.repair-orders.documents.store', $repairOrder),
        [
            'file' => UploadedFile::fake()->create('warranty.pdf', 120, 'application/pdf'),
            'title' => 'ASC Vehicle Protection Plan',
            'type' => DocumentType::Warranty->value,
        ],
    )->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    $document = Document::query()->first();
    expect($document)->not->toBeNull()
        ->and((int) $document->customer_id)->toBe((int) $customer->id)
        ->and((int) $document->repair_order_id)->toBe((int) $repairOrder->id)
        ->and($document->customer_visible)->toBeFalse()
        ->and($document->source)->toBe(DocumentSource::Upload)
        ->and(DocumentEvent::query()->where('document_id', $document->id)->where('type', DocumentEventType::Uploaded->value)->exists())->toBeTrue()
        ->and(DocumentEvent::query()->where('document_id', $document->id)->where('type', DocumentEventType::Attached->value)->exists())->toBeTrue();

    Storage::disk('local')->assertExists($document->storage_path);
});

test('upload from customer can omit repair order', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $this->actingAs($admin)->post(
        route('operations.customers.documents.store', $customer),
        [
            'file' => UploadedFile::fake()->create('registration.pdf', 80, 'application/pdf'),
            'title' => 'Vehicle Registration',
            'type' => DocumentType::Registration->value,
        ],
    )->assertRedirect();

    $document = Document::query()->first();
    expect($document->customer_id)->toBe($customer->id)
        ->and($document->repair_order_id)->toBeNull();
});

test('attach existing document to ro does not duplicate file', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer, $repairOrder] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('prior.pdf', 40, 'application/pdf'),
        $admin,
        DocumentType::ServiceRecord,
        'Previous repair invoice',
    );

    $path = $document->storage_path;

    $attached = app(AttachDocumentToRepairOrderAction::class)->handle($document, $repairOrder, $admin);

    expect((int) $attached->repair_order_id)->toBe((int) $repairOrder->id)
        ->and($attached->storage_path)->toBe($path)
        ->and(Document::query()->count())->toBe(1);
});

test('multi-page camera capture creates one pdf', function () {
    if (! class_exists(Imagick::class)) {
        $this->markTestSkipped('Imagick required for scan assembly');
    }

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer, $repairOrder] = documentsFixture();

    $document = app(StoreScannedDocumentAction::class)->handle(
        $customer,
        [
            UploadedFile::fake()->image('page1.jpg', 800, 1100),
            UploadedFile::fake()->image('page2.jpg', 800, 1100),
        ],
        $admin,
        DocumentType::Warranty,
        'Two page warranty',
        null,
        $repairOrder,
    );

    expect($document->source)->toBe(DocumentSource::Scan)
        ->and($document->content_type)->toBe('application/pdf')
        ->and($document->page_count)->toBe(2)
        ->and(DocumentEvent::query()->where('document_id', $document->id)->where('type', DocumentEventType::Scanned->value)->exists())->toBeTrue();

    Storage::disk('local')->assertExists($document->storage_path);
});

test('download requires authorization and customer match', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer, $repairOrder] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('align.pdf', 50, 'application/pdf'),
        $admin,
        DocumentType::Alignment,
        'Alignment Report',
        null,
        $repairOrder,
    );

    $this->actingAs($admin)
        ->get(route('operations.customers.documents.download', [$customer, $document]))
        ->assertOk();

    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Person',
        'phone' => '555-9999',
        'customer_type' => 'Retail',
    ]);

    $this->actingAs($admin)
        ->get(route('operations.customers.documents.show', [$other, $document]))
        ->assertNotFound();
});

test('customer repair order mismatch is rejected', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();
    $foreign = repairOrderForEstimateWorkspace();

    expect(fn () => app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
        $admin,
        DocumentType::General,
        'Mismatch',
        null,
        $foreign,
    ))->toThrow(ValidationException::class);
});

test('default visibility is shop only and show to customer is gated', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('ins.pdf', 20, 'application/pdf'),
        $admin,
        DocumentType::Insurance,
        'Insurance card',
    );

    expect($document->customer_visible)->toBeFalse();

    $this->actingAs($admin)->post(
        route('operations.customers.documents.visibility', [$customer, $document]),
        ['customer_visible' => 1],
    )->assertRedirect();

    expect($document->fresh()->customer_visible)->toBeTrue()
        ->and(DocumentEvent::query()->where('document_id', $document->id)->where('type', DocumentEventType::Shared->value)->exists())->toBeTrue();

    app(SetDocumentVisibilityAction::class)->handle($document->fresh(), false, $admin);
    expect($document->fresh()->customer_visible)->toBeFalse();
});

test('retire hides without deleting file bytes', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('old.pdf', 20, 'application/pdf'),
        $admin,
        DocumentType::Other,
        'Old paperwork',
    );

    $path = $document->storage_path;
    app(RetireDocumentAction::class)->handle($document, $admin);

    expect($document->fresh()->trashed())->toBeTrue()
        ->and(Document::query()->count())->toBe(0)
        ->and(DocumentEvent::query()->where('document_id', $document->id)->where('type', DocumentEventType::Retired->value)->exists())->toBeTrue();

    Storage::disk('local')->assertExists($path);

    $this->actingAs($admin)
        ->get(route('operations.customers.documents.show', [$customer, $document->id]))
        ->assertNotFound();
});

test('invalid mime and oversized upload are rejected', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $this->actingAs($admin)->post(
        route('operations.customers.documents.store', $customer),
        [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'title' => 'Bad file',
            'type' => DocumentType::General->value,
        ],
    )->assertSessionHasErrors('file');

    $this->actingAs($admin)->post(
        route('operations.customers.documents.store', $customer),
        [
            'file' => UploadedFile::fake()->create('huge.pdf', 30_000, 'application/pdf'),
            'title' => 'Too big',
            'type' => DocumentType::General->value,
        ],
    )->assertSessionHasErrors('file');
});

test('guest cannot stream guessed document ids', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('secret.pdf', 20, 'application/pdf'),
        $admin,
        DocumentType::General,
        'Secret',
    );

    auth()->logout();

    $this->get(route('operations.customers.documents.show', [$customer, $document]))
        ->assertRedirect();
});

test('rotate writes new storage object and records event', function () {
    if (! class_exists(Imagick::class)) {
        $this->markTestSkipped('Imagick required for rotation');
    }

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->image('scan.jpg', 600, 800),
        $admin,
        DocumentType::Warranty,
        'Upside down warranty',
    );

    $oldPath = $document->storage_path;
    $rotated = app(RotateDocumentAction::class)->handle($document, 'right', $admin);

    expect($rotated->storage_path)->not->toBe($oldPath)
        ->and(DocumentEvent::query()->where('document_id', $rotated->id)->where('type', DocumentEventType::Rotated->value)->exists())->toBeTrue();

    Storage::disk('local')->assertExists($rotated->storage_path);
    Storage::disk('local')->assertMissing($oldPath);
});

test('advisor can email paperwork from the viewer with attachment', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer, $repairOrder] = documentsFixture();
    $customer->forceFill(['email' => 'dana@example.test'])->save();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('warranty.pdf', 40, 'application/pdf'),
        $admin,
        DocumentType::Warranty,
        'ASC Vehicle Protection Plan',
        repairOrder: $repairOrder,
    );

    Mail::fake();

    $this->actingAs($admin)
        ->from(route('operations.customers.documents.viewer', [$customer, $document]))
        ->post(route('operations.customers.documents.email', [$customer, $document]), [
            'message' => 'Here is your warranty paperwork.',
        ])
        ->assertRedirect(route('operations.customers.documents.viewer', [$customer, $document]))
        ->assertSessionHas('status');

    Mail::assertSent(DocumentCustomerMail::class, function (DocumentCustomerMail $mail) use ($customer, $document, $repairOrder): bool {
        return $mail->hasTo('dana@example.test')
            && $mail->document->is($document)
            && $mail->customer->is($customer)
            && $mail->repairOrder?->is($repairOrder)
            && $mail->staffNote === 'Here is your warranty paperwork.';
    });

    expect(DocumentEvent::query()
        ->where('document_id', $document->id)
        ->where('type', DocumentEventType::Emailed->value)
        ->exists())->toBeTrue();

    expect(ConversationMessage::query()
        ->where('channel', OperationalCommunicationChannel::Email->value)
        ->where('body', 'like', '%ASC Vehicle Protection Plan%')
        ->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('operations.customers.documents.viewer', [$customer, $document]))
        ->assertOk()
        ->assertSee('Email log', false)
        ->assertSee('dana@example.test', false)
        ->assertSee('Here is your warranty paperwork.', false);
});

test('email requires recipient when customer has no email on file', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('registration.pdf', 20, 'application/pdf'),
        $admin,
        DocumentType::Registration,
        'Vehicle Registration',
    );

    Mail::fake();

    $this->actingAs($admin)
        ->from(route('operations.customers.documents.viewer', [$customer, $document]))
        ->post(route('operations.customers.documents.email', [$customer, $document]), [])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('document viewer shows email form', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer] = documentsFixture();

    $document = app(StoreDocumentAction::class)->handle(
        $customer,
        UploadedFile::fake()->create('alignment.pdf', 20, 'application/pdf'),
        $admin,
        DocumentType::Alignment,
        'Alignment printout',
    );

    $this->actingAs($admin)
        ->get(route('operations.customers.documents.viewer', [$customer, $document]))
        ->assertOk()
        ->assertSee('Email document', false)
        ->assertSee(route('operations.customers.documents.email', [$customer, $document]), false);
});
