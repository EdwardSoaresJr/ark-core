<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Operations\Business\BusinessWorkspaceAccess;
use App\Ark\Operations\Labor\TechnicianTimeClockProjection;
use App\Ark\Operations\Learn\ArkademyUrls;
use App\Ark\Operations\Scoreboard\ShopOperatingScoreboardAccess;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Work\WorkQueue;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\DevRolePretend;
use App\Ark\Runtime\DemoInstall;
use App\Models\User;
use App\Support\Branding\Branding;
use Illuminate\Http\Request;

/**
 * The staff navigation. The legacy rail and the Tabler sidebar both render these sections, and both account menus
 * render these entries; each shell chooses its own markup and icons.
 */
final class StaffNavigation
{
    /**
     * Sections in display order. Each holds only the items the user may see, and a section with no visible item is
     * left out. `key` names the destination for icons and tests; `icon` is its Tabler icon. The shell supplies the
     * communications destination, which depends on the user's attention queue.
     *
     * @return list<array{label: ?string, items: list<array{key: string, label: string, url: string, icon: string, active: bool, live_count: bool}>}>
     */
    public static function sections(?User $user, Request $request, string $communicationsUrl): array
    {
        $can = fn (ArkCapability $capability): bool => $user?->can($capability->value) ?? false;
        $operates = $can(ArkCapability::OperationsAccess);
        $produces = $can(ArkCapability::ProductionAccess);
        $managesSettings = $can(ArkCapability::SettingsManage);
        $managesCustomers = $can(ArkCapability::CustomersManage);
        $viewsRepairOrders = $can(ArkCapability::RepairOrdersView);
        $usesBusinessWorkspace = BusinessWorkspaceAccess::allows($user);
        $usesOwnerWorkspace = $user?->canAccessOwnerWorkspace() ?? false;
        $showsArkademy = ($produces || $operates) && ! DemoInstall::isDemo() && config('ark.arkademy_in_navigation');

        $sections = [
            [
                'label' => null,
                'visible' => $produces || ShopOperatingScoreboardAccess::allows($user),
                'items' => [
                    self::item('dashboard', 'Dashboard', 'dashboard', route('operations.dashboard'), ['operations.dashboard', 'operations.today', 'operations.briefing', 'operations.owner.scoreboard'], true),
                    self::item('time-clock', 'Time clock', 'clock', route('operations.time-clock.index'), ['operations.time-clock.*'], TechnicianTimeClockProjection::canAccess($user)),
                ],
            ],
            [
                'label' => 'Operations',
                'visible' => $operates || $produces,
                'items' => [
                    self::item('job-board', 'Job Board', 'layout-grid', route('operations.index'), ['operations.index', 'operations.workboard'], $operates),
                    self::item('workboard', 'Job Board', 'layout-grid', route('operations.workboard'), ['operations.workboard'], $produces && ! $operates),
                    self::item('work-queues', 'Work Queues', 'list-check', route('operations.work.queue', WorkQueue::Tasks), ['operations.work.queue'], $operates),
                    self::item('schedule', 'Schedule', 'calendar', route('operations.appointments.index'), ['operations.appointments.*'], $operates && ShopSettings::current()->appointmentsEnabled()),
                    self::item('communications', 'Communications', 'message', $communicationsUrl, ['operations.communications.*'], $operates, liveCount: true),
                ],
            ],
            [
                'label' => 'Records',
                'visible' => true,
                'items' => [
                    self::item('repair-orders', 'Repair Orders', 'file-text', route('operations.repair-orders.index'), ['operations.repair-orders.*'], $viewsRepairOrders),
                    self::item('vehicles', 'Vehicles', 'car', route('operations.vehicles.search'), ['operations.vehicles.*'], $managesCustomers),
                    self::item('customers', 'Customers', 'users', route('operations.customers.search'), ['operations.customers.*'], $managesCustomers),
                ],
            ],
            [
                'label' => 'Business',
                'visible' => $usesBusinessWorkspace || $managesSettings,
                'items' => [
                    self::item('cockpit', 'Cockpit', 'gauge', route('operations.business'), ['operations.business'], $usesBusinessWorkspace),
                    self::item('reports', 'Reports', 'chart-bar', route('operations.reports.index'), ['operations.reports.*'], $can(ArkCapability::FinancialView)),
                    self::item('day-review', 'Day Review', 'calendar-stats', route('operations.owner.day-review'), ['operations.owner.day-review', 'operations.owner.bookend'], $usesOwnerWorkspace),
                    self::item('tech-production', 'Tech production', 'tool', route('operations.owner.technician-production.index'), ['operations.owner.technician-production.*'], $usesOwnerWorkspace),
                ],
            ],
            [
                'label' => 'System',
                'visible' => true,
                'items' => [
                    self::item('learn', Branding::learnName(), 'school', ArkademyUrls::staffNavUrl(), ['operations.learn.*'], $showsArkademy),
                    self::item('stations', 'Stations & Phones', 'device-desktop', route('operations.shop.communications'), ['operations.shop.*'], $managesSettings),
                    self::item('settings', 'Settings', 'settings', route('operations.settings.shop.edit'), ['operations.settings.*'], $managesSettings),
                ],
            ],
        ];

        $visible = [];

        foreach ($sections as $section) {
            if (! $section['visible']) {
                continue;
            }

            $items = [];

            foreach ($section['items'] as $item) {
                if ($item['visible']) {
                    $items[] = [
                        'key' => $item['key'],
                        'label' => $item['label'],
                        'url' => $item['url'],
                        'icon' => $item['icon'],
                        'active' => $request->routeIs(...$item['patterns']),
                        'live_count' => $item['live_count'],
                    ];
                }
            }

            if ($items !== []) {
                $visible[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $visible;
    }

    /**
     * Account menu entries in display order. `kind` tells the shell how to render the entry: a link (`url`), a form
     * that posts to `url`, or a client action handled by the shell's scripts.
     *
     * @return list<array{key: string, kind: 'link'|'post'|'action', label: string, url?: string, alternate_label?: string}>
     */
    public static function accountMenu(User $user, WorkstationPresence $presence): array
    {
        $entries = [];

        if ($presence->workstation !== null) {
            $entries[] = ['key' => 'switch-station', 'kind' => 'action', 'label' => 'Switch station'];
        }

        if ($user->hasOperatorPin()) {
            $entries[] = ['key' => 'change-pin', 'kind' => 'action', 'label' => 'Change station PIN', 'url' => route('profile.edit', ['tab' => 'workstation-pin'])];
        }

        $entries[] = ['key' => 'profile', 'kind' => 'link', 'label' => 'Profile', 'url' => route('profile.edit')];
        $entries[] = ['key' => 'theme', 'kind' => 'action', 'label' => 'Dark mode', 'alternate_label' => 'Light mode'];

        if (DevRolePretend::switcherVisible($user)) {
            $entries[] = DevRolePretend::isActive()
                ? ['key' => 'dev-role', 'kind' => 'post', 'label' => 'Return to admin access', 'url' => route('dev-role-pretend.clear')]
                : ['key' => 'dev-role', 'kind' => 'post', 'label' => 'Test as technician', 'url' => route('dev-role-pretend.technician')];
        }

        $entries[] = ['key' => 'sign-out', 'kind' => 'post', 'label' => 'Sign out', 'url' => route('logout')];

        return $entries;
    }

    /**
     * @param  list<string>  $patterns
     * @return array{key: string, label: string, url: string, icon: string, patterns: list<string>, live_count: bool, visible: bool}
     */
    private static function item(string $key, string $label, string $icon, string $url, array $patterns, bool $visible, bool $liveCount = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'patterns' => $patterns,
            'live_count' => $liveCount,
            'visible' => $visible,
        ];
    }
}
