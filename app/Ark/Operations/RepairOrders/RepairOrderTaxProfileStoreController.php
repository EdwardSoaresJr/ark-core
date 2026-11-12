<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\EstimatePricing\TaxSourcingLocationKind;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderTaxProfileStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SelectTaxProfileOnRepairOrderAction $action,
    ): RedirectResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);

        $data = $request->validate([
            'tax_profile_key' => ['required', 'string', 'max:64'],
            'tax_sourcing_location_kind' => ['required', 'string', Rule::in(array_column(TaxSourcingLocationKind::cases(), 'value'))],
            'service_address_line1' => ['nullable', 'string', 'max:255'],
            'service_address_line2' => ['nullable', 'string', 'max:255'],
            'service_address_city' => ['nullable', 'string', 'max:255'],
            'service_address_state' => ['nullable', 'string', 'max:32'],
            'service_address_postal_code' => ['nullable', 'string', 'max:32'],
            'service_address_county' => ['nullable', 'string', 'max:255'],
            'confirm_jurisdiction' => ['nullable', 'boolean'],
            RepairOrderConcurrency::FIELD => ['required', 'integer'],
        ]);

        RepairOrderConcurrency::assertMatches($repairOrder, (int) $data[RepairOrderConcurrency::FIELD]);

        $kind = TaxSourcingLocationKind::from($data['tax_sourcing_location_kind']);
        $serviceAddress = [
            'line1' => $data['service_address_line1'] ?? null,
            'line2' => $data['service_address_line2'] ?? null,
            'city' => $data['service_address_city'] ?? null,
            'state' => $data['service_address_state'] ?? null,
            'postal_code' => $data['service_address_postal_code'] ?? null,
            'county' => $data['service_address_county'] ?? null,
        ];

        $action->execute(
            $repairOrder,
            (string) $data['tax_profile_key'],
            $kind,
            $request->user(),
            $serviceAddress,
            confirmed: (bool) ($data['confirm_jurisdiction'] ?? true),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Tax jurisdiction updated for this repair order.');
    }
}
