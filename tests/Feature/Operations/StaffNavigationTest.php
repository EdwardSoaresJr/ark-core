<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Staff\StaffNavigation;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['appointments_enabled' => true]);
    ShopSettings::forgetCurrent();
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $this->app->instance(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null)
        {
            return new HtmlString('');
        }
    });
});

/**
 * @return array<string, list<string>>
 */
function navigationKeys(?User $user, ?Request $request = null): array
{
    $keys = [];

    foreach (StaffNavigation::sections($user, $request ?? request(), 'https://shop.test/attention') as $section) {
        $keys[$section['label'] ?? ''] = array_column($section['items'], 'key');
    }

    return $keys;
}

function requestFor(string $url): Request
{
    $request = Request::create($url);
    $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

    return $request;
}

/**
 * Section labels and [url, label] pairs in the order a shell renders them.
 *
 * @return list<string|array{0: string, 1: string}>
 */
function renderedNavigation(string $html, string $start, string $end, string $sectionPattern, string $itemPattern): array
{
    $from = strpos($html, $start);
    expect($from)->not->toBeFalse();
    $nav = substr($html, $from, strpos($html, $end, $from) - $from);

    preg_match_all("/{$sectionPattern}|{$itemPattern}/s", $nav, $matches, PREG_SET_ORDER);

    return array_map(
        fn (array $match): string|array => ($match['section'] ?? '') !== ''
            ? html_entity_decode($match['section'])
            : [html_entity_decode($match['url']), html_entity_decode(trim(strip_tags($match['label'])))],
        $matches,
    );
}

/**
 * @return list<string|array{0: string, 1: string}>
 */
function modelNavigation(User $user, string $communicationsUrl): array
{
    $expected = [];

    foreach (StaffNavigation::sections($user, request(), $communicationsUrl) as $section) {
        if ($section['label'] !== null) {
            $expected[] = $section['label'];
        }

        foreach ($section['items'] as $item) {
            $expected[] = [$item['url'], $item['label']];
        }
    }

    return $expected;
}

test('each role sees its destinations in order', function (callable $makeUser, array $expected): void {
    expect(navigationKeys($makeUser()))->toBe($expected);
})->with([
    'admin' => [fn () => User::factory()->create()->assignRole(ArkRole::Admin->value), [
        '' => ['dashboard', 'time-clock'],
        'Operations' => ['job-board', 'work-queues', 'schedule', 'communications'],
        'Records' => ['repair-orders', 'vehicles', 'customers'],
        'Business' => ['cockpit', 'reports', 'day-review', 'tech-production'],
        'System' => ['learn', 'stations', 'settings'],
    ]],
    'advisor' => [fn () => User::factory()->create()->assignRole(ArkRole::Advisor->value), [
        '' => ['dashboard', 'time-clock'],
        'Operations' => ['job-board', 'work-queues', 'schedule', 'communications'],
        'Records' => ['repair-orders', 'vehicles', 'customers'],
        'Business' => ['cockpit', 'reports'],
        'System' => ['learn'],
    ]],
    'technician' => [fn () => User::factory()->create()->assignRole(ArkRole::Technician->value), [
        '' => ['dashboard', 'time-clock'],
        'Operations' => ['workboard'],
        'Records' => ['repair-orders'],
        'System' => ['learn'],
    ]],
    'customer' => [fn () => User::factory()->create()->assignRole(ArkRole::Customer->value), []],
    'production access only' => [fn () => User::factory()->create()->givePermissionTo('production.access'), [
        '' => ['dashboard'],
        'Operations' => ['workboard'],
        'System' => ['learn'],
    ]],
    'scoreboard only' => [fn () => User::factory()->create()->givePermissionTo('scoreboard.operational.view'), [
        '' => ['dashboard'],
    ]],
    'repair order viewer only' => [fn () => User::factory()->create()->givePermissionTo('repair_orders.view'), [
        'Records' => ['repair-orders'],
    ]],
    'signed out' => [fn () => null, []],
]);

test('a section with no destination for the user is left out of both shells', function (): void {
    $user = User::factory()->create()->givePermissionTo('settings.manage');
    $this->actingAs($user);

    expect(navigationKeys($user))->toBe(['System' => ['stations', 'settings']]);

    expect(Blade::render('<x-operations.app>body</x-operations.app>'))
        ->toContain('<p class="ops-rail-section__label">System</p>')
        ->not->toContain('<p class="ops-rail-section__label">Business</p>');
    expect(Blade::render('<x-operations.app ui="tabler">body</x-operations.app>'))
        ->toContain('<div class="subheader px-3 pb-1">System</div>')
        ->not->toContain('<div class="subheader px-3 pb-1">Business</div>');
});

test('schedule follows the appointments setting', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    ShopSettings::current()->update(['appointments_enabled' => false]);
    ShopSettings::forgetCurrent();

    expect(navigationKeys($advisor)['Operations'])->toBe(['job-board', 'work-queues', 'communications']);
});

test('the destination for the current route is the only active one', function (string $url, string $activeKey): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $active = [];
    foreach (StaffNavigation::sections($admin, requestFor($url), 'https://shop.test/attention') as $section) {
        foreach ($section['items'] as $item) {
            if ($item['active']) {
                $active[] = $item['key'];
            }
        }
    }

    expect($active)->toBe([$activeKey]);
})->with([
    'today' => [fn () => route('operations.today'), 'dashboard'],
    'workboard' => [fn () => route('operations.workboard'), 'job-board'],
    'appointment list' => [fn () => route('operations.appointments.index'), 'schedule'],
    'work queue' => [fn () => route('operations.work.queue', 'decisions'), 'work-queues'],
    'repair order list' => [fn () => route('operations.repair-orders.index'), 'repair-orders'],
    'customer hub' => [fn () => route('operations.customers.show', 1), 'customers'],
    'vehicle search' => [fn () => route('operations.vehicles.search'), 'vehicles'],
    'bookend' => [fn () => route('operations.owner.bookend'), 'day-review'],
    'settings' => [fn () => route('operations.settings.shop.edit'), 'settings'],
]);

test('both shells render exactly the shared sections', function (callable $makeUser): void {
    $user = $makeUser();
    $this->actingAs($user);
    $legacyHtml = Blade::render('<x-operations.app>body</x-operations.app>');
    preg_match('/href="([^"]+)"\s+data-ops-comms-nav-link/', $legacyHtml, $communications);
    $expected = modelNavigation($user, html_entity_decode($communications[1] ?? ''));

    $legacy = renderedNavigation(
        $legacyHtml,
        '<nav aria-label="Primary operations" class="ops-rail-nav">',
        '</nav>',
        '<p class="ops-rail-section__label">(?<section>[^<]+)<\/p>',
        '<a\s+href="(?<url>[^"]+)"[^>]*class="ops-rail-link[^"]*"\s*>.*?<span(?: class="ops-rail-link__label")?>(?<label>[^<]+)<\/span>',
    );
    $tabler = renderedNavigation(
        Blade::render('<x-operations.app ui="tabler">body</x-operations.app>'),
        'id="ark-sidebar-menu"',
        '</ul>',
        '<div class="subheader px-3 pb-1">(?<section>[^<]+)<\/div>',
        '<a\s+href="(?<url>[^"]+)"\s+class="nav-link".*?<span class="nav-link-title">(?<label>[^<]+)<\/span>',
    );

    expect($expected)->not->toBeEmpty()
        ->and($legacy)->toBe($expected)
        ->and($tabler)->toBe($expected);
})->with([
    'admin' => fn () => User::factory()->create()->assignRole(ArkRole::Admin->value),
    'advisor' => fn () => User::factory()->create()->assignRole(ArkRole::Advisor->value),
    'technician' => fn () => User::factory()->create()->assignRole(ArkRole::Technician->value),
    'production access only' => fn () => User::factory()->create()->givePermissionTo('production.access'),
    'advisor with settings' => fn () => User::factory()->create()->assignRole(ArkRole::Advisor->value)->givePermissionTo('settings.manage'),
]);

test('each shell marks the current destination', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.shop.communications'))
        ->assertOk()
        ->assertSee('href="'.route('operations.shop.communications').'" class="ops-rail-link ops-rail-link--active"', false);

    $this->actingAs($admin)
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSeeInOrder(['<li class="nav-item active">', 'href="'.route('operations.customers.search').'"', 'aria-current="page"'], false);
});

test('every destination has a legacy rail icon', function (): void {
    $keys = collect([ArkRole::Admin, ArkRole::Technician])
        ->flatMap(fn (ArkRole $role): array => array_merge(...array_values(navigationKeys(User::factory()->create()->assignRole($role->value)))))
        ->unique();

    expect($keys)->toHaveCount(17);

    foreach ($keys as $key) {
        expect(Blade::render('<x-operations.rail-icon :name="$name" />', ['name' => $key]))->toContain('<svg');
    }

    expect(fn () => Blade::render('<x-operations.rail-icon name="unknown" />'))->toThrow(Exception::class);
});

test('both account menus render the shared entries', function (): void {
    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $admin->setOperatorPin('4821');
    $admin->save();
    $this->actingAs($admin);

    $entries = StaffNavigation::accountMenu($admin, WorkstationPresence::resolve(request()));

    expect(array_column($entries, 'key'))->toBe(['change-pin', 'profile', 'theme', 'dev-role', 'sign-out']);

    foreach (['<x-operations.app>body</x-operations.app>', '<x-operations.app ui="tabler">body</x-operations.app>'] as $shell) {
        $html = Blade::render($shell);

        expect($html)
            ->toContain('data-profile-pin-url="'.route('profile.edit', ['tab' => 'workstation-pin']).'"')
            ->toContain('href="'.route('profile.edit').'"')
            ->toContain('window.arkToggleTheme()')
            ->toContain('action="'.route('dev-role-pretend.technician').'"')
            ->toContain('action="'.route('logout').'"')
            ->not->toContain('data-ark-workstation-switch');
    }
});

test('advisors and technicians see the repair order board under one name', function (): void {
    $labels = function (User $user): array {
        $found = [];
        foreach (StaffNavigation::sections($user, request(), 'https://shop.test/attention') as $section) {
            foreach ($section['items'] as $item) {
                $found[$item['key']] = $item['label'];
            }
        }

        return $found;
    };

    $advisor = $labels(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    $technician = $labels(User::factory()->create()->assignRole(ArkRole::Technician->value));

    expect($advisor['job-board'])->toBe('Job Board')
        ->and($technician['workboard'])->toBe('Job Board')
        ->and($technician)->not->toHaveKey('work-queues')
        ->and($advisor['work-queues'])->toBe('Work Queues');
});
