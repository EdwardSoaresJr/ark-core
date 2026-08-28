<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderLinePartMetadata
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(Request $request): array
    {
        if ($request->input('type') !== RepairOrderLineType::Part->value) {
            return [];
        }

        return [
            'part_source' => ['nullable', Rule::enum(PartLineSource::class)],
            'part_classification' => ['nullable', Rule::enum(PartLineClassification::class)],
            'part_warranty_impact' => ['nullable', Rule::enum(PartLineWarrantyImpact::class)],
            'customer_part_posture' => [
                'nullable',
                Rule::enum(CustomerPartPosture::class),
                Rule::requiredIf(fn (): bool => $request->input('part_source') === PartLineSource::CustomerSupplied->value),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{part_source: string, part_classification: ?string, part_warranty_impact: string}
     */
    public static function persistenceAttributes(array $data, bool $isPartLine): array
    {
        if (! $isPartLine) {
            return [
                'part_source' => PartLineSource::ShopSupplied->value,
                'part_classification' => null,
                'part_warranty_impact' => PartLineWarrantyImpact::None->value,
            ];
        }

        return [
            'part_source' => PartLineSource::fromStored($data['part_source'] ?? null)->value,
            'part_classification' => PartLineClassification::tryFromStored($data['part_classification'] ?? null)?->value,
            'part_warranty_impact' => PartLineWarrantyImpact::fromStored($data['part_warranty_impact'] ?? null)->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function initialProcurementState(array $data, bool $isPartLine): PartProcurementState
    {
        if (! $isPartLine) {
            return PartProcurementState::None;
        }

        $source = PartLineSource::fromStored($data['part_source'] ?? null);

        if ($source !== PartLineSource::CustomerSupplied) {
            return PartProcurementState::None;
        }

        $posture = CustomerPartPosture::tryFromInput($data['customer_part_posture'] ?? null);

        return $posture?->procurementState() ?? PartProcurementState::AwaitingCustomer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveProcurementStateUpdate(array $data, RepairOrderLine $line, bool $isPartLine): ?PartProcurementState
    {
        if (! $isPartLine) {
            return null;
        }

        $nextSource = PartLineSource::fromStored($data['part_source'] ?? null);
        $currentSource = $line->part_source ?? PartLineSource::ShopSupplied;
        $posture = CustomerPartPosture::tryFromInput($data['customer_part_posture'] ?? null);

        if ($nextSource === PartLineSource::CustomerSupplied) {
            if ($posture instanceof CustomerPartPosture) {
                return $posture->procurementState();
            }

            if ($currentSource !== PartLineSource::CustomerSupplied) {
                return $line->procurementState()->isShopProcurementState() || $line->procurementState() === PartProcurementState::None
                    ? PartProcurementState::AwaitingCustomer
                    : null;
            }

            return null;
        }

        if ($currentSource === PartLineSource::CustomerSupplied && $nextSource === PartLineSource::ShopSupplied) {
            return match ($line->procurementState()) {
                PartProcurementState::AwaitingCustomer,
                PartProcurementState::Received,
                PartProcurementState::None => PartProcurementState::None,
                default => null,
            };
        }

        return null;
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorHelpOverviewItems(): array
    {
        return [
            [
                'label' => 'Part source',
                'detail' => 'Who supplied the part — shop inventory/order vs customer brought in.',
            ],
            [
                'label' => 'Part type',
                'detail' => 'OEM, aftermarket replacement, or performance/custom — not recommendation status.',
            ],
            [
                'label' => 'Warranty impact',
                'detail' => 'How this part affects warranty posture on the line — separate from scope billing.',
            ],
        ];
    }
}
