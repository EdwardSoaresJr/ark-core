<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\Workboard\JobBoardLayout;
use Illuminate\Http\Request;

/**
 * Collection indexes (Customers, Vehicles, Repair Orders) resolve through
 * {@see UiLayoutPreference}. This class adapts module enums and layout types
 * so controllers keep a stable return shape without a second preference store.
 *
 * @return array{
 *     mode: string,
 *     layout: CollectionLayout|null,
 *     explicit: bool,
 *     module: CollectionLayoutModule,
 *     scope: array{tenant: string, user: string}
 * }
 */
final class CollectionLayoutPreference
{
    public const MODE_AUTO = UiLayoutPreference::MODE_AUTO;

    /**
     * @return array{
     *     mode: string,
     *     layout: CollectionLayout|null,
     *     explicit: bool,
     *     module: CollectionLayoutModule,
     *     scope: array{tenant: string, user: string}
     * }
     */
    public static function resolve(Request $request, CollectionLayoutModule $module): array
    {
        $resolved = UiLayoutPreference::resolve($request, self::toUiModule($module));
        $layout = $resolved['layout'] instanceof JobBoardLayout
            ? CollectionLayout::tryParse($resolved['layout']->value)
            : null;

        return [
            'mode' => $resolved['mode'],
            'layout' => $layout,
            'explicit' => $resolved['explicit'],
            'module' => $module,
            'scope' => $resolved['scope'],
        ];
    }

    public static function queue(CollectionLayoutModule $module, CollectionLayout $layout): void
    {
        UiLayoutPreference::queueLegacyCookie(
            self::toUiModule($module),
            JobBoardLayout::parse($layout->value),
        );
    }

    /**
     * Cookie names that must stay readable by first-party layout JS.
     *
     * @return list<string>
     */
    public static function cookieNames(): array
    {
        return array_values(array_filter([
            UiLayoutModule::Customers->legacyCookieName(),
            UiLayoutModule::Vehicles->legacyCookieName(),
            UiLayoutModule::RepairOrders->legacyCookieName(),
        ]));
    }

    public static function toUiModule(CollectionLayoutModule $module): UiLayoutModule
    {
        return match ($module) {
            CollectionLayoutModule::Customers => UiLayoutModule::Customers,
            CollectionLayoutModule::Vehicles => UiLayoutModule::Vehicles,
            CollectionLayoutModule::RepairOrders => UiLayoutModule::RepairOrders,
        };
    }
}
