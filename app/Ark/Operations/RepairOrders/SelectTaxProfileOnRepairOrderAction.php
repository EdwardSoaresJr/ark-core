<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\EstimatePricing\ShopTaxCatalog;
use App\Ark\Operations\EstimatePricing\TaxJurisdictionMatcher;
use App\Ark\Operations\EstimatePricing\TaxProfile;
use App\Ark\Operations\EstimatePricing\TaxSourcingLocationKind;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Explicitly selects/confirm a catalog tax profile onto a living RO as TaxProfile schema 2.
 * City/ZIP suggestions never auto-assign — this action is the confirmation path.
 */
final class SelectTaxProfileOnRepairOrderAction
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly TaxJurisdictionMatcher $matcher,
    ) {}

    /**
     * @param  array{line1?: ?string, line2?: ?string, city?: ?string, state?: ?string, postal_code?: ?string, county?: ?string}|null  $serviceAddress
     */
    public function execute(
        RepairOrder $repairOrder,
        string $profileKey,
        TaxSourcingLocationKind $locationKind,
        ?User $actor = null,
        ?array $serviceAddress = null,
        bool $confirmed = true,
    ): RepairOrder {
        $repairOrder->ensureOpenForEditing();

        if ($repairOrder->isTerminal()) {
            throw new RuntimeException('Terminal repair orders cannot change tax jurisdiction.');
        }

        return DB::transaction(function () use ($repairOrder, $profileKey, $locationKind, $serviceAddress, $confirmed): RepairOrder {
            /** @var RepairOrder $locked */
            $locked = RepairOrder::query()->whereKey($repairOrder->id)->lockForUpdate()->firstOrFail();
            $locked->ensureOpenForEditing();

            $settings = ShopSettings::current();
            $profiles = ShopTaxCatalog::profilesFromShopSettings($settings);
            $selected = collect($profiles)->first(
                fn (array $profile): bool => (string) ($profile['key'] ?? '') === $profileKey
                    && (bool) ($profile['is_active'] ?? true),
            );

            if (! is_array($selected)) {
                throw ValidationException::withMessages([
                    'tax_profile_key' => 'Choose an active tax profile.',
                ]);
            }

            $address = $this->addressForKind($locked, $locationKind, $serviceAddress);
            $suggestion = $this->matcher->suggest($settings, $address);
            $candidateKeys = collect($suggestion['candidates'])->pluck('key')->all();
            $matcherNote = $suggestion['note'];
            if ($candidateKeys !== [] && ! in_array($profileKey, $candidateKeys, true)) {
                $matcherNote = 'Selected profile differs from address suggestions. '.$matcherNote;
            }

            $sourcing = [
                'location_kind' => $locationKind->value,
                'selection_status' => $confirmed ? 'confirmed' : 'selected',
                'confirmed_at' => $confirmed ? now()->toIso8601String() : null,
                'matcher_note' => $matcherNote,
                'matcher_ambiguous' => (bool) $suggestion['ambiguous'],
                'address' => $address,
            ];

            $stamp = TaxProfile::fromCatalogProfile(
                $selected,
                enabled: (bool) $settings->tax_enabled,
                documentPresentation: ShopTaxCatalog::documentPresentation($settings),
                sourcing: $sourcing,
            )->toArray();

            $locked->forceFill(['tax_profile' => $stamp])->save();
            $this->calculator->ensureFinancialTotalsAreCurrent(
                $locked->fresh(['lines', 'concerns', 'customer']),
            );

            return $locked->fresh(['lines', 'concerns', 'customer', 'vehicle']);
        });
    }

    /**
     * @param  array{line1?: ?string, line2?: ?string, city?: ?string, state?: ?string, postal_code?: ?string, county?: ?string}|null  $serviceAddress
     * @return array{state: ?string, county: ?string, city: ?string, postal_code: ?string, line1?: ?string, line2?: ?string}
     */
    private function addressForKind(
        RepairOrder $repairOrder,
        TaxSourcingLocationKind $kind,
        ?array $serviceAddress,
    ): array {
        $settings = ShopSettings::current();
        $repairOrder->loadMissing('customer');

        return match ($kind) {
            TaxSourcingLocationKind::Shop => [
                'line1' => $settings->address_line_1,
                'line2' => $settings->address_line_2,
                'city' => $settings->city,
                'state' => $settings->state,
                'postal_code' => $settings->postal_code,
                'county' => null,
            ],
            TaxSourcingLocationKind::CustomerBilling => [
                'line1' => $repairOrder->customer?->address_line_1,
                'line2' => $repairOrder->customer?->address_line_2,
                'city' => $repairOrder->customer?->city,
                'state' => $repairOrder->customer?->state,
                'postal_code' => $repairOrder->customer?->postal_code,
                'county' => null,
            ],
            TaxSourcingLocationKind::ServiceAddress, TaxSourcingLocationKind::Manual => [
                'line1' => $serviceAddress['line1'] ?? null,
                'line2' => $serviceAddress['line2'] ?? null,
                'city' => $serviceAddress['city'] ?? null,
                'state' => $serviceAddress['state'] ?? null,
                'postal_code' => $serviceAddress['postal_code'] ?? null,
                'county' => $serviceAddress['county'] ?? null,
            ],
        };
    }
}
