<?php

use App\Ark\Operations\Communications\ConversationWorkboardPresenter;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\ConversationTimeline;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentEvent;
use App\Ark\Operations\Documents\DocumentEventType;
use App\Ark\Operations\Documents\DocumentType;
use App\Ark\Operations\Documents\StoreDocumentAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
    Storage::fake('local');
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    $this->advisor = User::factory()->create(['name' => 'Molly Advisor'])->assignRole(ArkRole::Advisor->value);
});

/**
 * A customer with a text conversation of their own and a repair order on their vehicle.
 *
 * @return array{0: Customer, 1: RepairOrder, 2: Conversation}
 */
function recipientFixture(User $advisor): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Bo',
        'last_name' => 'Vale',
        'phone' => '7195550181',
        'email' => 'bo.vale@example.test',
        'customer_type' => 'Retail',
    ]);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'plate' => 'RCP181', 'year' => 2018, 'make' => 'Ford', 'model' => 'F-150']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Warranty claim',
    ])->fresh();

    $phoneThread = app(ConversationResolver::class)->forCustomer($customer);
    app(ConversationRecorder::class)->record($phoneThread, app(ConversationParticipantResolver::class)->customer($phoneThread, $customer),
        OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Inbound, 'Is the truck ready?', now()->subHour());

    return [$customer, $repairOrder, $phoneThread];
}

function emailDocument(RepairOrder $repairOrder, User $actor, string $recipient, string $label = 'Estimate'): ConversationMessage
{
    return app(ConversationRecorder::class)->recordRepairOrderEmail($repairOrder, $actor, $recipient,
        $label.' emailed to '.$recipient.'.', metadata: ['document_label' => $label]);
}

function linkedTo(Conversation $conversation, object $model): bool
{
    return ConversationLink::query()
        ->where('conversation_id', $conversation->id)
        ->where('linkable_type', $model->getMorphClass())
        ->where('linkable_id', $model->id)
        ->exists();
}

/**
 * The selected conversation's thread and context lanes, without the list of every conversation beside them.
 */
function workspacePage(User $viewer, Conversation $conversation): string
{
    $page = (string) test()->actingAs($viewer)
        ->get(route('operations.communications.inbox', ['filter' => 'all', 'conversation' => $conversation->id]))
        ->assertOk()
        ->getContent();

    return substr($page, (int) strpos($page, 'id="ops-comms-workspace-thread"'));
}

test('A: a document emailed to a warranty company stays the warranty company\'s thread, on the repair order and in the customer\'s history', function () {
    [$customer, $repairOrder, $phoneThread] = recipientFixture($this->advisor);

    $message = emailDocument($repairOrder, $this->advisor, 'Claims@Warranty.Test');
    $thread = $message->conversation;

    expect($thread->contact_surface)->toBe(ConversationContactSurface::Email)
        ->and($thread->contact_address)->toBe('claims@warranty.test')
        ->and(linkedTo($thread, $repairOrder))->toBeTrue()
        ->and(linkedTo($thread, $repairOrder->vehicle))->toBeTrue()
        ->and(linkedTo($thread, $customer))->toBeFalse()
        ->and($thread->linkedCustomerId())->toBeNull()
        ->and($message->metadata['recipient_email'])->toBe('claims@warranty.test')
        ->and($message->metadata['repair_order_id'])->toBe($repairOrder->id)
        ->and(ConversationParticipant::query()->where('conversation_id', $thread->id)
            ->where('participant_type', ConversationParticipantType::Customer->value)
            ->whereNull('customer_id')->value('display_name'))->toBe('claims@warranty.test');

    $customer->refresh();
    expect($customer->email)->toBe('bo.vale@example.test')
        ->and($customer->phone)->toBe('7195550181')
        ->and($phoneThread->fresh()->contact_address)->toBe('7195550181');

    $timeline = app(ConversationTimeline::class);
    expect($timeline->forRepairOrder($repairOrder)->pluck('id'))->toContain($message->id)
        ->and($timeline->forCustomerRelationship($customer, '7195550181', 20)->pluck('id'))->toContain($message->id);

    $page = workspacePage($this->advisor, $thread);
    expect($page)->toContain('data-comms-context-thread-email>claims@warranty.test')
        ->and($page)->not->toContain('data-comms-context-thread-phone')
        ->and($page)->not->toContain('data-comms-context-primary-phone')
        ->and($page)->not->toContain('tel:+17195550181')
        ->and($page)->toContain('data-comms-composer-unavailable="This conversation is by email. Text the customer from the Customer panel."')
        ->and($page)->toContain('Document emailed')
        ->and($page)->toContain('Document: Estimate')
        ->and($page)->toContain('To claims@warranty.test')
        ->and($page)->toContain('by Molly Advisor')
        ->and(app(ConversationWorkboardPresenter::class)->present($thread, 'waiting_customer')['headline'])->toBe('claims@warranty.test');
});

test('A: the warranty email sent from the document viewer goes through the same recorder', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    [$customer, $repairOrder] = recipientFixture($this->advisor);
    $document = app(StoreDocumentAction::class)->handle($customer, UploadedFile::fake()->create('claim.pdf', 40, 'application/pdf'),
        $admin, DocumentType::Warranty, 'Warranty claim', repairOrder: $repairOrder);
    Storage::disk('local')->put($document->storage_path, '%PDF-1.4 claim');
    usePlatformPortalMail();

    $this->actingAs($admin)
        ->post(route('operations.customers.documents.email', [$customer, $document]), ['email' => 'claims@warranty.test'])
        ->assertSessionHas('status');

    assertPlatformTransactionalMail('document.send', 'claims@warranty.test');

    $thread = Conversation::query()->where('contact_address', 'claims@warranty.test')->sole();
    $message = $thread->messages()->sole();

    expect(linkedTo($thread, $customer))->toBeFalse()
        ->and(linkedTo($thread, $repairOrder))->toBeTrue()
        ->and($message->metadata['document_id'])->toBe($document->id)
        ->and($message->metadata['document_label'])->toBe('Warranty claim')
        ->and($message->metadata['recipient_email'])->toBe('claims@warranty.test')
        ->and(DocumentEvent::query()->where('document_id', $document->id)->where('type', DocumentEventType::Emailed->value)->exists())->toBeTrue();

    $entry = app(ConversationMessageEventMapper::class)->map($message);
    expect($entry->links)->toBe(['Open warranty claim' => route('operations.customers.documents.show', [$customer, $document])]);
});

test('B: a document emailed to the customer\'s own address is the customer\'s thread', function () {
    [$customer, $repairOrder] = recipientFixture($this->advisor);

    $thread = emailDocument($repairOrder, $this->advisor, 'BO.VALE@example.test')->conversation;

    expect(linkedTo($thread, $customer))->toBeTrue()
        ->and($thread->linkedCustomerId())->toBe($customer->id)
        ->and(ConversationParticipant::query()->where('conversation_id', $thread->id)->whereNull('customer_id')
            ->where('participant_type', ConversationParticipantType::Customer->value)->exists())->toBeFalse();

    $page = workspacePage($this->advisor, $thread);
    expect($page)->toContain('Bo Vale')
        ->and($page)->toContain('tel:+17195550181')
        ->and($page)->toContain(route('operations.customers.show', $customer).'?compose=text#customer-communication')
        ->and($page)->not->toContain('data-comms-context-thread-email');
});

test('C: one document to the customer and to the warranty company keeps two separate threads on the repair order', function () {
    [$customer, $repairOrder] = recipientFixture($this->advisor);

    $toCustomer = emailDocument($repairOrder, $this->advisor, 'bo.vale@example.test');
    $toWarranty = emailDocument($repairOrder, $this->advisor, 'claims@warranty.test');

    expect($toCustomer->conversation_id)->not->toBe($toWarranty->conversation_id)
        ->and($toCustomer->conversation->linkedCustomerId())->toBe($customer->id)
        ->and($toWarranty->conversation->linkedCustomerId())->toBeNull()
        ->and(app(ConversationTimeline::class)->forRepairOrder($repairOrder)->pluck('id')->all())
        ->toContain($toCustomer->id, $toWarranty->id);

    $warrantyThread = $toWarranty->conversation;
    $page = workspacePage($this->advisor, $warrantyThread);
    expect($page)->not->toContain('bo.vale@example.test')
        ->and($page)->toContain('To claims@warranty.test');
});

test('D: an email thread never offers a text to one of the customer\'s phones', function () {
    [$customer, $repairOrder] = recipientFixture($this->advisor);
    $otherPhone = app(ConversationResolver::class)->forContactKey(ConversationContactSurface::Phone, '7195550999');
    ConversationLink::query()->create(['conversation_id' => $otherPhone->id, 'linkable_type' => $customer->getMorphClass(), 'linkable_id' => $customer->id]);

    $warrantyThread = emailDocument($repairOrder, $this->advisor, 'claims@warranty.test')->conversation;
    $ownThread = emailDocument($repairOrder, $this->advisor, 'bo.vale@example.test')->conversation;

    $page = workspacePage($this->advisor, $warrantyThread);
    expect($page)->not->toContain('href="#comms-thread-composer"')
        ->and($page)->not->toContain('?compose=text#customer-communication')
        ->and($page)->not->toContain('tel:+1719555');

    $page = workspacePage($this->advisor, $ownThread);
    expect($page)->not->toContain('aria-label="Text" title="Text" @click="contextOpen = false" href="#comms-thread-composer"')
        ->and($page)->toContain('?compose=text#customer-communication')
        ->and($page)->toContain('data-comms-composer-unavailable="This conversation is by email. Text the customer from the Customer panel."');
});

test('E: a recipient who is not a customer is never made one', function () {
    [, $repairOrder] = recipientFixture($this->advisor);
    $customers = Customer::query()->count();

    $thread = emailDocument($repairOrder, $this->advisor, 'fleet@vendor.test')->conversation;
    emailDocument($repairOrder, $this->advisor, 'fleet@vendor.test');

    expect(Customer::query()->count())->toBe($customers)
        ->and($thread->messages()->count())->toBe(2)
        ->and(ConversationParticipant::query()->where('conversation_id', $thread->id)
            ->where('participant_type', ConversationParticipantType::Customer->value)->count())->toBe(1)
        ->and(ConversationParticipant::query()->where('conversation_id', $thread->id)->whereNotNull('customer_id')->exists())->toBeFalse();
});

test('F: replies stay behind the email feature and document links behind the viewer\'s permissions', function () {
    [$customer, $repairOrder] = recipientFixture($this->advisor);
    $thread = emailDocument($repairOrder, $this->advisor, 'claims@warranty.test')->conversation;

    $page = workspacePage($this->advisor, $thread);
    expect($page)->toContain('data-comms-composer-unavailable="Email replies are not set up for this shop."')
        ->and($page)->not->toContain('data-comms-email-composer');

    $message = app(ConversationRecorder::class)->recordRepairOrderEmail($repairOrder, $this->advisor, 'claims@warranty.test',
        'Estimate emailed.', metadata: ['document_label' => 'Estimate', 'estimate_document_id' => 991]);

    $this->actingAs(User::factory()->create());
    expect(app(ConversationMessageEventMapper::class)->map($message)->links)->toBe([]);

    $this->actingAs($this->advisor);
    expect(app(ConversationMessageEventMapper::class)->map($message)->links)
        ->toBe(['Open estimate' => route('operations.repair-orders.estimate-documents.show', [$repairOrder->id, 991])]);
});

test('G: existing threads attached to the wrong customer are reported, repaired and restored without losing history', function () {
    [$customer, $repairOrder, $phoneThread] = recipientFixture($this->advisor);

    // A thread as recorded before the fix: attached to the repair order's customer although sent to the warranty company.
    $legacy = app(ConversationResolver::class)->forEmail('claims@warranty.test');
    foreach ([$repairOrder, $customer, $repairOrder->vehicle] as $model) {
        ConversationLink::query()->create(['conversation_id' => $legacy->id, 'linkable_type' => $model->getMorphClass(), 'linkable_id' => $model->id]);
    }
    $legacyMessage = app(ConversationRecorder::class)->record($legacy, app(ConversationParticipantResolver::class)->system($legacy, 'Shop'),
        OperationalCommunicationChannel::Email, OperationalCommunicationDirection::Outbound, 'Estimate emailed to claims@warranty.test.',
        metadata: ['repair_order_id' => $repairOrder->id, 'actor_user_id' => $this->advisor->id]);

    // One the recipient answered: someone must look at it.
    $replied = app(ConversationResolver::class)->forEmail('adjuster@warranty.test');
    foreach ([$repairOrder, $customer] as $model) {
        ConversationLink::query()->create(['conversation_id' => $replied->id, 'linkable_type' => $model->getMorphClass(), 'linkable_id' => $model->id]);
    }
    app(ConversationRecorder::class)->record($replied, app(ConversationParticipantResolver::class)->customer($replied, null, 'adjuster@warranty.test'),
        OperationalCommunicationChannel::Email, OperationalCommunicationDirection::Inbound, 'Approved.');

    // Read before any repair: the thread is no longer presented as the customer's.
    expect($legacy->linkedCustomerId())->toBeNull()
        ->and(workspacePage($this->advisor, $legacy))->not->toContain('tel:+17195550181');

    $this->artisan('ark:documents:repair-email-recipients')
        ->expectsOutputToContain('1 safe to repair, 1 for manual review.')
        ->expectsOutputToContain('Dry run: nothing changed.')
        ->assertSuccessful();
    expect(linkedTo($legacy, $customer))->toBeTrue();

    $this->artisan('ark:documents:repair-email-recipients --apply')->assertSuccessful();

    expect(linkedTo($legacy, $customer))->toBeFalse()
        ->and(linkedTo($legacy, $repairOrder))->toBeTrue()
        ->and(linkedTo($replied, $customer))->toBeTrue()
        ->and(ConversationMessage::query()->whereKey($legacyMessage->id)->exists())->toBeTrue()
        ->and(app(ConversationTimeline::class)->forCustomerRelationship($customer, '7195550181', 20)->pluck('id'))->toContain($legacyMessage->id)
        ->and($phoneThread->messages()->count())->toBe(1);

    $this->artisan('ark:documents:repair-email-recipients')
        ->expectsOutputToContain('0 safe to repair, 1 for manual review.')
        ->assertSuccessful();

    $backup = collect(File::files(storage_path('app/remediation')))
        ->filter(fn ($file): bool => str_starts_with($file->getFilename(), 'document-email-recipients-'))
        ->sortByDesc(fn ($file): int => $file->getMTime())
        ->first();

    $this->artisan('ark:documents:repair-email-recipients --rollback='.$backup->getPathname())->assertSuccessful();

    expect(linkedTo($legacy, $customer))->toBeTrue()
        ->and(ConversationParticipant::query()->where('conversation_id', $legacy->id)->whereNull('customer_id')
            ->where('participant_type', ConversationParticipantType::Customer->value)->exists())->toBeFalse()
        ->and(ConversationMessage::query()->whereKey($legacyMessage->id)->exists())->toBeTrue();

    File::delete($backup->getPathname());
});
