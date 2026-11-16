<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

test('compose opens a phone conversation without creating a customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $beforeCustomers = Customer::query()->count();

    $response = $this->actingAs($advisor)
        ->post(route('operations.communications.compose'), [
            'phone' => '(719) 555-8801',
        ]);

    $conversation = app(ConversationResolver::class)->findForPhone('7195558801');

    expect($conversation)->not->toBeNull()
        ->and($conversation->contact_surface)->toBe(ConversationContactSurface::Phone)
        ->and($conversation->contact_address)->toBe('7195558801')
        ->and(Customer::query()->count())->toBe($beforeCustomers);

    $response->assertRedirect(route('operations.communications.inbox', [
        'filter' => 'needs',
        'conversation' => $conversation->id,
        'compose' => 1,
    ]));
});

test('compose opens an email conversation without creating a customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $beforeCustomers = Customer::query()->count();

    $response = $this->actingAs($advisor)
        ->post(route('operations.communications.compose'), [
            'email' => 'Parts.Desk@Example.COM',
        ]);

    $conversation = app(ConversationResolver::class)->findForContactKey(
        ConversationContactSurface::Email,
        'parts.desk@example.com',
    );

    expect($conversation)->not->toBeNull()
        ->and($conversation->contact_address)->toBe('parts.desk@example.com')
        ->and(Customer::query()->count())->toBe($beforeCustomers);

    $response->assertRedirect(route('operations.communications.inbox', [
        'filter' => 'needs',
        'conversation' => $conversation->id,
        'compose' => 1,
    ]));
});

test('compose reuses an existing phone conversation instead of duplicating', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $existing = app(ConversationResolver::class)->forPhone('7195558802');

    $this->actingAs($advisor)
        ->post(route('operations.communications.compose'), [
            'phone' => '719-555-8802',
        ])
        ->assertRedirect(route('operations.communications.inbox', [
            'filter' => 'needs',
            'conversation' => $existing->id,
            'compose' => 1,
        ]));

    expect(Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', '7195558802')
        ->count())->toBe(1);
});

test('linking a customer later keeps the same conversation history', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = app(ConversationResolver::class)->forPhone('7195558803');
    $customer = Customer::query()->create([
        'first_name' => 'Later',
        'last_name' => 'Linked',
        'phone' => '7195558803',
    ]);

    app(ConversationLinker::class)->link($conversation, $customer);

    expect($conversation->fresh()->links()->where('linkable_type', $customer->getMorphClass())->count())->toBe(1)
        ->and(app(ConversationResolver::class)->findForPhone('7195558803')?->id)->toBe($conversation->id);
});

test('global search offers compose for an unknown phone number', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->getJson(route('operations.search', ['q' => '7195558804']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'message',
            'compose_phone' => '7195558804',
            'compose_email' => null,
        ]);
});

test('global search offers compose for an unknown email address', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->getJson(route('operations.search', ['q' => 'vendor@example.test']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'message',
            'compose_email' => 'vendor@example.test',
            'compose_phone' => null,
        ]);
});

test('new message form is available without a customer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.compose.create'))
        ->assertOk()
        ->assertSee('New Message', false)
        ->assertSee('Phone number', false)
        ->assertSee('Email address', false);
});

test('compose still opens a customer conversation when customer_id is provided', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Existing',
        'last_name' => 'Patron',
        'phone' => '7195558805',
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.communications.compose'), [
            'customer_id' => $customer->id,
        ])
        ->assertRedirect();

    $conversation = app(ConversationResolver::class)->findForPhone('7195558805');
    expect($conversation)->not->toBeNull();
});

test('compose rejects empty contact input', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->from(route('operations.communications.compose.create'))
        ->post(route('operations.communications.compose'), [])
        ->assertRedirect(route('operations.communications.compose.create'))
        ->assertSessionHasErrors('phone');
});

test('compose GET prefills phone for call-to-text without creating a conversation', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.compose.create', [
            'phone' => '7195558806',
            'start' => 1,
        ]))
        ->assertOk()
        ->assertSee('value="7195558806"', false)
        ->assertSee('Open conversation', false);

    expect(app(ConversationResolver::class)->findForPhone('7195558806'))->toBeNull();
});

test('guests cannot open compose or create conversations', function (): void {
    $this->get(route('operations.communications.compose.create', ['phone' => '7195558807']))
        ->assertRedirect();

    $this->post(route('operations.communications.compose'), ['phone' => '7195558807'])
        ->assertRedirect();

    expect(app(ConversationResolver::class)->findForPhone('7195558807'))->toBeNull();
});
