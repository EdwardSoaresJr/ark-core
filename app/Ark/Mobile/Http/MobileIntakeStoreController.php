<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeRepairOrderProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Intake\AdvisorIntakeService;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileIntakeStoreController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        AdvisorIntakeService $intake,
        MobileIntakeRepairOrderProjection $projection,
    ): JsonResponse {
        abort_unless($access->canPerformIntake($request->user()), 403);

        $billingClassNames = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'visit_reason' => ['nullable', 'string', 'max:5000'],
            // Compat: older mobile clients may still send concern rows — map text into visit_reason only.
            'concerns' => ['nullable', 'array'],
            'concerns.*.customer_states' => ['nullable', 'string', 'max:5000'],
            'advisor_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'mileage_in' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'visit_mode' => ['required', Rule::in(['waiting_here', 'drop_off', 'needs_shuttle', 'tow_incoming'])],
            'billing_class' => ['nullable', Rule::in($billingClassNames)],
        ]);

        $visitMode = $data['visit_mode'];
        $billingClass = trim((string) ($data['billing_class'] ?? ''));
        $visitReason = trim((string) ($data['visit_reason'] ?? ''));

        if ($visitReason === '' && is_array($data['concerns'] ?? null)) {
            $parts = [];
            foreach ($data['concerns'] as $concern) {
                $text = trim((string) ($concern['customer_states'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
            $visitReason = implode("\n\n", $parts);
        }

        $payload = [
            'customer_id' => (int) $data['customer_id'],
            'vehicle_id' => (int) $data['vehicle_id'],
            'visit_reason' => $visitReason !== '' ? $visitReason : null,
            'waiting_here' => $visitMode === 'waiting_here',
            'drop_off' => $visitMode === 'drop_off',
            'needs_shuttle' => $visitMode === 'needs_shuttle',
            'tow_incoming' => $visitMode === 'tow_incoming',
            'billing_class' => $billingClass !== '' ? $billingClass : null,
            'fleet' => strcasecmp($billingClass, 'Fleet') === 0,
            'warranty' => strcasecmp($billingClass, 'Warranty') === 0,
            'assigned_technician_id' => filled($data['assigned_technician_id'] ?? null)
                ? (int) $data['assigned_technician_id']
                : null,
            'mileage_in' => filled($data['mileage_in'] ?? null) ? (int) $data['mileage_in'] : null,
        ];

        $repairOrder = $intake->create($payload, $request->user());

        return response()->json($projection->created($repairOrder), 201);
    }
}
