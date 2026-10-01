<?php

namespace App\Ark\Operations\Workspace;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Intake\IntakeWorkspaceSession;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Server-side helpers for ARK operational workspace tabs (route detection, boot metadata).
 */
final class WorkspaceTabSupport
{
    public const INTAKE_ENTITY_ID = 'service';

    /**
     * @return array<string, mixed>|null
     */
    public static function detectFromRequest(Request $request): ?array
    {
        if (! config('ark_workspace_tabs.enabled', true)) {
            return null;
        }

        $path = trim($request->path(), '/');

        foreach (config('ark_workspace_tabs.path_patterns', []) as $rule) {
            $pattern = (string) ($rule['pattern'] ?? '');
            $type = (string) ($rule['type'] ?? '');
            $idGroup = (int) ($rule['id_group'] ?? 1);

            if ($pattern === '' || $type === '') {
                continue;
            }

            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            if ($type === 'intake') {
                return self::buildIntakePayload($request);
            }

            $id = (string) ($matches[$idGroup] ?? '');

            if ($id === '') {
                continue;
            }

            if ($type === 'customer' && $request->filled('vehicle')) {
                return self::buildVehiclePayload(
                    (string) $request->integer('vehicle'),
                    $request,
                );
            }

            return self::buildEntityPayload($type, $id, $request);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildIntakePayload(Request $request): array
    {
        $id = IntakeWorkspaceSession::idFromRequest($request) ?? IntakeWorkspaceSession::LAUNCHER_ENTITY_ID;

        return [
            'key' => self::makeKey('intake', $id),
            'entityType' => 'intake',
            'entityId' => $id,
            'route' => self::intakeWorkspaceRoute($request),
            'title' => self::intakeTabTitle($request),
            'subtitle' => self::intakeSubtitle($request),
            'type' => 'intake',
            'id' => $id,
        ];
    }

    public static function intakeTabTitle(Request $request): string
    {
        $customerId = $request->integer('customer_id') ?: null;

        if ($customerId) {
            $customer = Customer::query()->find($customerId);

            if ($customer !== null) {
                return $customer->name;
            }
        }

        return 'Check In';
    }

    public static function intakeWorkspaceRoute(Request $request): string
    {
        $path = '/'.trim($request->path(), '/');

        if ($path === '/app/intake/new') {
            return self::relativeRequestRoute($request);
        }

        return '/app/intake/new';
    }

    public static function intakeSubtitle(Request $request): string
    {
        $customerId = $request->integer('customer_id') ?: null;
        $vehicleId = $request->integer('vehicle_id') ?: null;
        $searchQuery = trim((string) $request->query('q', ''));

        if ($customerId) {
            $customer = Customer::query()->find($customerId);

            if ($customer === null) {
                return 'Recognize customer';
            }

            if ($vehicleId) {
                $vehicle = Vehicle::query()
                    ->where('customer_id', $customer->id)
                    ->find($vehicleId);

                if ($vehicle !== null) {
                    return $customer->name.' · '.$vehicle->display_name;
                }
            }

            if ($request->boolean('select_vehicle')) {
                return $customer->name.' · Choose vehicle';
            }

            return $customer->name.' · Vehicle';
        }

        if ($searchQuery !== '') {
            $trimmed = mb_strlen($searchQuery) > 28
                ? mb_substr($searchQuery, 0, 25).'…'
                : $searchQuery;

            return 'Search: '.$trimmed;
        }

        return 'Recognize customer';
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildEntityPayload(string $type, string $id, Request $request): array
    {
        $route = self::relativeRequestRoute($request);
        $key = self::makeKey($type, $id);

        if ($type === 'repair_order') {
            $route = self::repairOrderWorkspaceRoute($id, $request);
        }

        if ($type === 'intake') {
            $route = self::intakeWorkspaceRoute($request);
        }

        return [
            'key' => $key,
            'entityType' => $type,
            'entityId' => $id,
            'route' => $route,
            'title' => self::defaultTitle($type, $id),
            'subtitle' => '',
            'type' => $type,
            'id' => $id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildVehiclePayload(string $vehicleId, Request $request): array
    {
        $route = self::relativeRequestRoute($request);

        return [
            'key' => self::makeKey('vehicle', $vehicleId),
            'entityType' => 'vehicle',
            'entityId' => $vehicleId,
            'route' => $route,
            'title' => self::defaultTitle('vehicle', $vehicleId),
            'subtitle' => '',
            'type' => 'vehicle',
            'id' => $vehicleId,
        ];
    }

    public static function makeKey(string $type, string $id): string
    {
        return $type.':'.$id;
    }

    public static function relativeRequestRoute(Request $request): string
    {
        $path = '/'.trim($request->path(), '/');
        $query = $request->getQueryString();

        return $query !== null && $query !== '' ? $path.'?'.$query : $path;
    }

    public static function repairOrderWorkspaceRoute(string $shopNumber, Request $request): string
    {
        return '/app/repair-orders/'.$shopNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $userId = auth()->id();
        $storageKey = 'ark_ws_v2_'.($userId ?: 'guest');

        return [
            'enabled' => (bool) config('ark_workspace_tabs.enabled', true),
            'maxTabs' => (int) config('ark_workspace_tabs.max_tabs', 12),
            'tabMinWidth' => (int) config('ark_workspace_tabs.tab_min_width', 88),
            'desktopMinWidth' => (int) config('ark_workspace_tabs.desktop_min_width', 1024),
            'persistLocal' => (bool) config('ark_workspace_tabs.persist_local', true),
            'interceptLinks' => (bool) config('ark_workspace_tabs.intercept_links', true),
            'storageKey' => $storageKey,
            'types' => config('ark_workspace_tabs.types', []),
            'dashboardUrl' => Route::has('operations.index')
                ? route('operations.index')
                : '/app',
            'pathPatterns' => array_values(config('ark_workspace_tabs.path_patterns', [])),
            'permanentPinned' => self::permanentPinnedClientConfig(),
            'dockedContextual' => self::dockedContextualClientConfig(),
            'excludedWorkspaceKeys' => array_values(config('ark_workspace_tabs.excluded_workspace_keys', [])),
            'activityUrl' => Route::has('operations.workspace.activity')
                ? route('operations.workspace.activity')
                : '/app/workspace/activity',
            'resetIntake' => (bool) session()->pull('workspace_reset_intake', false),
            'closeIntakeWorkspaceId' => session()->pull('workspace_close_intake_ws'),
        ];
    }

    public static function defaultTitle(string $type, string $id): string
    {
        return match ($type) {
            'intake' => 'Check In',
            'repair_order' => $id,
            'customer' => 'Customer #'.$id,
            'vehicle' => 'Vehicle #'.$id,
            'inbox' => 'Inbox #'.$id,
            'report' => match ($id) {
                'operations' => 'Operations',
                default => 'Report: '.$id,
            },
            default => ucfirst(str_replace('_', ' ', $type)).' #'.$id,
        };
    }

    /**
     * @param  array<string, mixed>|null  $boot
     * @return array<string, mixed>|null
     */
    public static function enrichBoot(?array $boot, ?Request $request = null): ?array
    {
        if ($boot === null) {
            return null;
        }

        $type = (string) ($boot['entityType'] ?? $boot['type'] ?? '');
        $id = (string) ($boot['entityId'] ?? $boot['id'] ?? '');

        if ($type === '' || $id === '') {
            return $boot;
        }

        $request ??= request();

        return match ($type) {
            'intake' => self::enrichIntakeBoot($boot, $request),
            'repair_order' => self::enrichRepairOrderBoot($boot, $id),
            'customer' => self::enrichCustomerBoot($boot, $id),
            'vehicle' => self::enrichVehicleBoot($boot, $id),
            default => $boot,
        };
    }

    /**
     * @param  array<string, mixed>  $boot
     * @return array<string, mixed>
     */
    private static function enrichIntakeBoot(array $boot, Request $request): array
    {
        $title = self::intakeTabTitle($request);
        $boot['title'] = $title;
        $boot['route'] = self::intakeWorkspaceRoute($request);
        $boot['subtitle'] = self::intakeSubtitle($request);
        $boot['customerName'] = $title !== 'Check In' ? $title : '';

        return $boot;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bootFromRepairOrder(RepairOrder $repairOrder, Request $request): array
    {
        $boot = self::buildEntityPayload(
            'repair_order',
            (string) $repairOrder->repair_order_id,
            $request,
        );

        return self::enrichRepairOrderBootFromModel($boot, $repairOrder);
    }

    /**
     * @param  array<string, mixed>  $boot
     * @return array<string, mixed>
     */
    public static function enrichRepairOrderBootFromModel(array $boot, RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $shortCustomer = self::shortCustomerName($repairOrder->customer);
        $vehicleLabel = self::shortVehicleLabel($repairOrder->vehicle);

        $boot['title'] = $shortCustomer !== ''
            ? $repairOrder->repair_order_id.' · '.$shortCustomer
            : (string) $repairOrder->repair_order_id;
        $boot['subtitle'] = $vehicleLabel;
        $boot['customerName'] = $shortCustomer;
        $boot['signals'] = WorkspaceTabActivityResolver::repairOrderSignals($repairOrder);

        return $boot;
    }

    public static function shortCustomerName(?Customer $customer): string
    {
        if ($customer === null) {
            return '';
        }

        $first = trim((string) $customer->first_name);
        $last = trim((string) $customer->last_name);

        if ($first === '' && $last === '') {
            return '';
        }

        if ($last === '') {
            return $first;
        }

        if ($first === '') {
            return $last;
        }

        $initial = mb_strtoupper(mb_substr($last, 0, 1));

        return $first.' '.$initial.'.';
    }

    public static function shortVehicleLabel(?Vehicle $vehicle): string
    {
        if ($vehicle === null) {
            return '';
        }

        $year = trim((string) ($vehicle->year ?? ''));
        $make = trim((string) ($vehicle->make ?? ''));
        $model = trim((string) ($vehicle->model ?? ''));

        $parts = array_values(array_filter([$year, $make, $model], fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return trim((string) ($vehicle->display_name ?? ''));
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $boot
     * @return array<string, mixed>
     */
    private static function enrichRepairOrderBoot(array $boot, string $id): array
    {
        $repairOrder = RepairOrder::query()
            ->with(['customer', 'vehicle'])
            ->where('repair_order_id', (int) $id)
            ->first();

        if ($repairOrder === null) {
            return $boot;
        }

        return self::enrichRepairOrderBootFromModel($boot, $repairOrder);
    }

    /**
     * @param  array<string, mixed>  $boot
     * @return array<string, mixed>
     */
    private static function enrichCustomerBoot(array $boot, string $id): array
    {
        $customer = Customer::query()->find((int) $id);

        if ($customer === null) {
            return $boot;
        }

        $boot['title'] = $customer->name;
        $boot['subtitle'] = $customer->display_phone ?? '';

        return $boot;
    }

    /**
     * @param  array<string, mixed>  $boot
     * @return array<string, mixed>
     */
    private static function enrichVehicleBoot(array $boot, string $id): array
    {
        $vehicle = Vehicle::query()->with('customer')->find((int) $id);

        if ($vehicle === null) {
            return $boot;
        }

        $boot['title'] = $vehicle->operational_identity;
        $boot['subtitle'] = $vehicle->customer?->name ?? '';
        $boot['route'] = Route::has('operations.customers.show')
            ? route('operations.customers.show', [
                'customer' => $vehicle->customer_id,
                'vehicle' => $vehicle->id,
            ])
            : $boot['route'];

        return $boot;
    }

    /**
     * @return list<array{key: string, entityType: string, entityId: string, route: string, title: string, subtitle: string}>
     */
    public static function permanentPinnedClientConfig(): array
    {
        return collect(config('ark_workspace_tabs.permanent_pinned', []))
            ->map(function (array $spec): array {
                $entityType = (string) ($spec['entityType'] ?? '');
                $entityId = (string) ($spec['entityId'] ?? '');
                $route = (string) ($spec['route'] ?? '');

                if ($entityType === 'report' && $entityId === 'operations' && Route::has('operations.reports.operational')) {
                    $route = route('operations.reports.operational', [], false);
                }

                return [
                    'key' => (string) ($spec['key'] ?? self::makeKey($entityType, $entityId)),
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'route' => $route,
                    'title' => (string) ($spec['title'] ?? self::defaultTitle($entityType, $entityId)),
                    'subtitle' => (string) ($spec['subtitle'] ?? ''),
                ];
            })
            ->filter(fn (array $spec): bool => $spec['entityType'] !== '' && $spec['entityId'] !== '' && $spec['route'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, entityType: string, entityId: string, route: string, title: string, subtitle: string}>
     */
    public static function dockedContextualClientConfig(): array
    {
        return collect(config('ark_workspace_tabs.docked_contextual', []))
            ->map(function (array $spec): array {
                $entityType = (string) ($spec['entityType'] ?? '');
                $entityId = (string) ($spec['entityId'] ?? '');
                $route = (string) ($spec['route'] ?? '');

                if ($entityType === 'intake' && $entityId === self::INTAKE_ENTITY_ID && Route::has('operations.intake.create')) {
                    $route = route('operations.intake.create', [], false);
                }

                return [
                    'key' => (string) ($spec['key'] ?? self::makeKey($entityType, $entityId)),
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'route' => $route,
                    'title' => (string) ($spec['title'] ?? self::defaultTitle($entityType, $entityId)),
                    'subtitle' => (string) ($spec['subtitle'] ?? ''),
                ];
            })
            ->filter(fn (array $spec): bool => $spec['entityType'] !== '' && $spec['entityId'] !== '' && $spec['route'] !== '')
            ->values()
            ->all();
    }
}
