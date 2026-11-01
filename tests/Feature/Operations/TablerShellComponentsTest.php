<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function shellComponentsXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function shellComponentsHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

test('a Tabler page renders the shell components as Tabler markup outside .ark-legacy', function (): void {
    Workstation::query()->create([
        'shop_settings_id' => ShopSettings::reloadCurrent()->id,
        'name' => 'Front Counter',
        'is_active' => true,
    ]);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $html = $this->actingAs($admin)
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->getContent();
    $xpath = shellComponentsXpath($html);
    $insideLegacy = 'ancestor-or-self::*['.shellComponentsHasClass('ark-legacy').']';

    $hooks = [
        'channel strip' => '//*[@data-ark-channel-strip]',
        'workstation presence' => '//*[@data-ark-workstation-presence]',
        'station bind prompt' => '//*[@data-ws-bind-panel]',
        'change PIN overlay' => '//*[@data-ws-overlay="change-pin"]',
        'switch station overlay' => '//*[@data-ws-overlay="switch-station"]',
        'global search' => '//*[@data-ops-global-search]',
        'guide modal' => '//*[@x-data="arkLearnGuideModal()"]',
        'comms interrupts' => '//*[@x-data="arkCommsInterrupt()"]',
        'call queue poller' => '//*[@data-call-queue-root]',
        'image lightbox' => '//*[@x-data="arkImageLightbox()"]',
    ];

    foreach ($hooks as $component => $query) {
        $nodes = $xpath->query($query);

        expect($nodes->length)->toBe(1, "{$component} renders once")
            ->and($xpath->query($insideLegacy, $nodes->item(0))->length)->toBe(0, "{$component} is outside .ark-legacy");
    }

    foreach (['ws-presence', 'ops-topbar-comms', 'ops-global-search', 'ops-incoming-call-pop', 'ops-incoming-message-pop', 'ops-comms-interrupt-backdrop', 'ops-image-lightbox', 'ops-learn-guide-modal', 'ops-call-queue'] as $legacyClass) {
        expect($xpath->query('//*['.shellComponentsHasClass($legacyClass).']')->length)->toBe(0, "no {$legacyClass}");
    }

    foreach (['data-ws-change-pin-form', 'data-ws-switch-station-form', 'data-ws-bind-form'] as $form) {
        expect($xpath->query('//form[@'.$form.']//input[@name="_token"]')->length)->toBe(1, "{$form} keeps its token");
    }

    expect($xpath->query('//*[@data-ws-bind-form]//select[@name="workstation_id"]/option')->length)->toBe(2)
        ->and($xpath->query('//*[@data-ws-switch-station-form]//input[@type="radio"][@name="workstation_id"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ws-change-pin-form]//input[@data-ws-pin-field][@type="tel"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-ws-overlay][@hidden]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-ws-overlay]//*[@data-ws-action="close-overlay"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-ws-bind-panel]//*[@data-ws-action="dismiss-bind"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ws-change-pin-error][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-ark-workstation-presence]//*['.shellComponentsHasClass('modal').']')->length)->toBe(3);

    expect($html)
        ->toContain('data-unlock-url="'.route('operations.workstation.unlock').'"')
        ->toContain('data-bind-url="'.route('operations.workstation.bind').'"')
        ->toContain('data-bind-dismiss-url="'.route('operations.workstation.bind.dismiss').'"')
        ->toContain('data-pin-update-url="'.route('operations.workstation.pin.update').'"')
        ->toContain('data-search-url="'.route('operations.search').'"')
        ->toContain('data-compose-url="'.route('operations.communications.compose').'"')
        ->toContain('data-ops-global-search-ui="tabler"')
        ->toContain('id="ops-global-search-input"')
        ->toContain('data-ops-global-search-input')
        ->toContain('data-ops-global-search-results')
        ->toContain('data-ops-global-search-backdrop')
        ->toContain('data-ops-global-search-commands')
        ->toContain('x-ref="callInterruptDialog"')
        ->toContain('x-ref="messageInterruptDialog"')
        ->toContain('data-comms-interrupt-primary')
        ->toContain('@keydown.enter.prevent="activateInterruptPrimary($event)"')
        ->toContain(':data-ops-lightbox="attachment.url"')
        ->toContain('Mark Handled')
        ->toContain('x-ref="viewport"')
        ->toContain('@keydown.escape.window="close()"')
        ->toContain('x-html="html"')
        ->and(substr_count($html, 'id="ark-call-queue-bootstrap"'))->toBe(1)
        ->and(substr_count($html, 'x-data="arkCallQueue()"'))->toBe(1);
});

test('the overlays stack in the legacy order after the page', function (): void {
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $html = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->getContent();

    $positions = array_map(fn (string $hook): int|false => strpos($html, $hook), [
        'id="ark-sidebar-menu"',
        'data-ops-global-search-ui="tabler"',
        'x-data="arkLearnGuideModal()"',
        'x-data="arkCommsInterrupt()"',
        'data-call-queue-root',
        'x-data="arkImageLightbox()"',
    ]);

    expect($positions)->not->toContain(false);

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted);
});

test('the only islands left in the Tabler shell are the workspace tabs and the guide preview', function (): void {
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $html = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->getContent();
    $xpath = shellComponentsXpath($html);
    $islands = $xpath->query('//*['.shellComponentsHasClass('ark-legacy').']');

    $kept = [];

    foreach ($islands as $island) {
        $kept[] = match (true) {
            $xpath->query('.//*[@id="ops-workspace-tabs"]', $island)->length === 1 => 'workspace tabs',
            $xpath->query('ancestor::*[@data-ark-learn-guide-modal]', $island)->length === 1 => 'guide preview',
            $xpath->query('.//*', $island)->length === 0 => 'empty',
            default => 'unexpected',
        };
    }

    expect($kept)->toContain('workspace tabs')
        ->toContain('guide preview')
        ->not->toContain('unexpected');
});

test('an ARKademy page keeps the legacy shell components', function (): void {
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $html = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'getting-started']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-ops-mobile-nav')
        ->toContain('class="ws-presence"')
        ->toContain('class="ops-global-search"')
        ->toContain('ops-incoming-call-pop')
        ->toContain('class="ops-image-lightbox"')
        ->toContain('class="ops-learn-guide-modal"')
        ->toContain('ops-call-queue--poller-only')
        ->toContain('ops-topbar-comms')
        ->not->toContain('data-ark-channel-strip')
        ->not->toContain('data-ops-global-search-ui')
        ->not->toContain('data-ark-learn-guide-modal')
        ->not->toContain('data-ark-comms-interrupt')
        ->not->toContain('navbar-vertical')
        ->and(substr_count($html, 'x-data="arkCommsInterrupt()"'))->toBe(1)
        ->and(substr_count($html, 'x-data="arkCallQueue()"'))->toBe(1)
        ->and(substr_count($html, 'x-data="arkImageLightbox()"'))->toBe(1)
        ->and(substr_count($html, 'x-data="arkLearnGuideModal()"'))->toBe(1);
});

test('the comms pressure bar renders a Tabler alert or the legacy bar', function (bool $live, string $tablerTone): void {
    $pressure = [
        'count' => 2,
        'summary' => ['breakdown_label' => '2 SMS'],
        'attention_url' => '/app/communications/attention',
        'has_live_calls' => $live,
    ];

    $tabler = (string) $this->blade('<x-operations.comms-pressure-bar ui="tabler" :pressure="$pressure" />', ['pressure' => $pressure]);
    $legacy = (string) $this->blade('<x-operations.comms-pressure-bar :pressure="$pressure" />', ['pressure' => $pressure]);

    expect($tabler)->toContain('alert mb-0 '.$tablerTone)
        ->toContain('data-ops-comms-pressure-bar="'.($live ? 'live' : 'attention').'"')
        ->toContain('customers need you')
        ->toContain('· 2 SMS')
        ->toContain('href="/app/communications/attention" class="btn btn-sm">Open Communications</a>')
        ->not->toContain('class="ops-comms-pressure-bar')
        ->and($legacy)->toContain('ops-comms-pressure-bar '.($live ? 'ops-comms-pressure-bar--live' : 'ops-comms-pressure-bar--attention'))
        ->not->toContain('alert');
})->with([
    'attention' => [false, 'alert-danger'],
    'live call' => [true, 'alert-success'],
]);

test('global search finds rows by data hooks in both shells', function (): void {
    $script = file_get_contents(base_path('resources/js/ark-ops-global-search.js'));

    expect($script)
        ->toContain("root.dataset.opsGlobalSearchUi === 'tabler'")
        ->toContain("querySelectorAll('[data-ops-global-search-row][data-index]')")
        ->toContain("closest('[data-ops-global-search-compose]')")
        ->not->toContain("'.ops-global-search__row[data-index]'");
});
