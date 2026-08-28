<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('unified inbox filters separate needs and waiting with counts', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $shopCustomer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Shop',
        'phone' => '7195551001',
        'email' => 'sarah.shop@example.com',
    ]);
    $customerWait = Customer::query()->create([
        'first_name' => 'Carl',
        'last_name' => 'Customer',
        'phone' => '7195551002',
    ]);

    Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551001',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Shop,
        'posture_changed_at' => now()->subMinutes(2),
    ]);

    Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195551002',
        'status' => ConversationStatus::Open,
        'waiting_on' => ConversationWaitingOn::Customer,
        'posture_changed_at' => now()->subMinute(),
    ]);

    $all = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        null,
        null,
        null,
        listFilter: 'all',
    );
    expect($all['filter_counts']['needs'])->toBeGreaterThanOrEqual(1)
        ->and($all['filter_counts']['waiting'])->toBeGreaterThanOrEqual(1);

    $waitingOnly = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        null,
        null,
        null,
        listFilter: 'waiting',
    );
    expect(collect($waitingOnly['list_items'])->every(fn (array $item): bool => ($item['turn'] ?? '') === 'customer'))->toBeTrue()
        ->and($waitingOnly['list_filter'])->toBe('waiting');

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Needs attention', false)
        ->assertSee('Needs first response', false)
        ->assertSee('Sarah Shop', false)
        ->assertSee('(719) 555-1001', false);
});

test('h3 compose anywhere opens conversation thread for customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Compose',
        'phone' => '7195552002',
        'email' => 'molly.compose@example.com',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.communications.compose'), [
            'customer_id' => $customer->id,
        ])
        ->assertRedirect();

    $conversation = app(ConversationResolver::class)->findForPhone('7195552002');
    expect($conversation)->not->toBeNull();

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['conversation' => $conversation->id, 'filter' => 'needs']))
        ->assertOk()
        ->assertSee('Molly Compose', false)
        ->assertSee('(719) 555-2002', false)
        ->assertSee('molly.compose@example.com', false)
        ->assertSee('Shop Context', false)
        ->assertSee('Conversation', false)
        ->assertSee('Call', false)
        ->assertSee('Schedule', false);
});

test('h3 global search returns customer matches as json', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    Customer::query()->create([
        'first_name' => 'Searchable',
        'last_name' => 'Patron',
        'phone' => '7195553333',
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.search', ['q' => 'Searchable']))
        ->assertOk()
        ->assertJsonPath('count', fn ($count) => $count >= 1)
        ->assertJsonFragment(['label' => 'Searchable Patron']);
});
