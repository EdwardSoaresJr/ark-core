<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Printing\KeyTagPrintGate;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Labor\ShopFixedCostPeriod;
use App\Ark\Operations\Labor\ShopOverheadSnapshot;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Workstations\WorkstationPresenceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopOperationsSettingsController
{
    use Concerns\InteractsWithShopSettingsPersistence;
    public function __construct(
        private readonly EstimateDocumentService $estimateDocumentService,
        private readonly EstimateTotalsCalculator $estimateTotalsCalculator,
    ) {}

    protected function estimateDocuments(): EstimateDocumentService
    {
        return $this->estimateDocumentService;
    }

    protected function totalsCalculator(): EstimateTotalsCalculator
    {
        return $this->estimateTotalsCalculator;
    }

public function updateEstimates(Request $request): RedirectResponse
    {
        $settings = ShopSettings::current();

        $data = $request->validate([
            'estimate_disclaimer' => ['nullable', 'string'],
            'invoice_disclaimer' => ['nullable', 'string'],
            'recommendation_disclaimer' => ['nullable', 'string'],
            'authorization_language' => ['nullable', 'string'],
            'customer_type_disclaimers' => ['nullable', 'array'],
            'customer_type_disclaimers.*' => ['nullable', 'string'],
            'estimate_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'portal_signature_required' => ['nullable', 'boolean'],
            'customer_part_description_mode' => ['required', Rule::enum(\App\Ark\Operations\Parts\CustomerPartDescriptionMode::class)],
            'customer_part_show_manufacturer_number' => ['nullable', 'boolean'],
            'customer_part_show_supplier' => ['nullable', 'boolean'],
            'customer_part_show_supplier_sku' => ['nullable', 'boolean'],
            'customer_part_allow_description_override' => ['nullable', 'boolean'],
        ]);

        $settings->update([
            'estimate_disclaimer' => $data['estimate_disclaimer'] ?? null,
            'invoice_disclaimer' => $data['invoice_disclaimer'] ?? null,
            'recommendation_disclaimer' => $data['recommendation_disclaimer'] ?? null,
            'authorization_language' => $data['authorization_language'] ?? null,
            'portal_signature_required' => $request->boolean('portal_signature_required'),
            'customer_part_description_mode' => $data['customer_part_description_mode'],
            'customer_part_show_manufacturer_number' => $request->boolean('customer_part_show_manufacturer_number'),
            'customer_part_show_supplier' => $request->boolean('customer_part_show_supplier'),
            'customer_part_show_supplier_sku' => $request->boolean('customer_part_show_supplier_sku'),
            'customer_part_allow_description_override' => $request->boolean('customer_part_allow_description_override'),
            'customer_type_disclaimers' => $settings->normalizeCustomerTypeDisclaimerInput(
                $data['customer_type_disclaimers'] ?? [],
            ),
            'estimate_validity_days' => $data['estimate_validity_days'],
        ]);

        $this->syncOpenEstimateDocuments();

        return $this->redirectWithStatus('Document disclaimer settings saved.');
    }

public function updateWorkflow(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_visit_mode' => ['required', Rule::enum(RepairOrderVisitMode::class)],
            'default_recommendation_intent' => ['required', Rule::enum(RecommendationIntent::class)],
            'default_notes_private' => ['nullable', 'boolean'],
            'default_notes_visible_to_advisor' => ['nullable', 'boolean'],
            'default_notes_visible_to_technician' => ['nullable', 'boolean'],
            'default_notes_visible_to_customer' => ['nullable', 'boolean'],
            'key_tag_mileage_requirement' => ['nullable', Rule::in([KeyTagPrintGate::NONE, KeyTagPrintGate::MILEAGE_IN])],
        ]);

        $payload = [
            'default_visit_mode' => $data['default_visit_mode'],
            'default_recommendation_intent' => $data['default_recommendation_intent'],
        ];

        if (
            $request->exists('default_notes_visible_to_advisor')
            || $request->exists('default_notes_visible_to_technician')
            || $request->exists('default_notes_visible_to_customer')
        ) {
            $advisor = $request->boolean('default_notes_visible_to_advisor');
            $technician = $request->boolean('default_notes_visible_to_technician');
            $customer = $request->boolean('default_notes_visible_to_customer');
            if (! $advisor && ! $technician && ! $customer) {
                $advisor = true;
            }

            $payload['default_notes_visible_to_advisor'] = $advisor;
            $payload['default_notes_visible_to_technician'] = $technician;
            $payload['default_notes_visible_to_customer'] = $customer;
            $payload['default_notes_private'] = ! $customer;
        } else {
            $payload['default_notes_private'] = $request->boolean('default_notes_private');
        }

        if ($request->exists('key_tag_mileage_requirement')) {
            $payload['key_tag_mileage_requirement'] = $data['key_tag_mileage_requirement'] ?? KeyTagPrintGate::NONE;
        }

        ShopSettings::current()->update($payload);

        $this->syncOpenEstimateDocuments();

        return $this->redirectWithStatus('Workflow settings saved.');
    }

    public function updateAppointments(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'appointments_enabled' => ['nullable', 'boolean'],
            'appointment_slot_minutes' => ['required', 'integer', Rule::in(\App\Ark\Operations\Appointments\AppointmentSlotMinutes::ALLOWED)],
            'appointment_capacity_basis' => ['required', Rule::enum(\App\Ark\Operations\Appointments\AppointmentCapacityBasis::class)],
            'appointment_scheduling_target_percent' => ['required', 'integer', 'min:25', 'max:300'],
            'appointment_capacity_enforcement' => ['required', Rule::enum(\App\Ark\Operations\Appointments\AppointmentCapacityEnforcement::class)],
            'workstation_idle_lock_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'scheduling_hours_follow_shop' => ['nullable', 'boolean'],
            'scheduling_hours' => ['nullable', 'array'],
            'scheduling_hours.*.enabled' => ['nullable', 'boolean'],
            'scheduling_hours.*.open' => ['nullable', 'date_format:H:i'],
            'scheduling_hours.*.close' => ['nullable', 'date_format:H:i'],
            'appointment_request_availability' => ['nullable', 'array'],
            'appointment_request_availability.weekly' => ['nullable', 'array'],
            'appointment_request_availability.weekly.*.enabled' => ['nullable', 'boolean'],
            'appointment_request_availability.horizon_days' => ['nullable'],
            'appointment_request_availability.horizon_is_custom' => ['nullable', 'boolean'],
            'appointment_request_availability.horizon_custom_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'appointment_request_availability.minimum_notice_days' => ['nullable', 'integer', 'min:0', 'max:14'],
        ]);

        $settings = ShopSettings::current();

        $payload = [
            'appointments_enabled' => $request->boolean('appointments_enabled'),
            'appointment_slot_minutes' => (int) $data['appointment_slot_minutes'],
            'appointment_capacity_basis' => $data['appointment_capacity_basis'] instanceof \BackedEnum
                ? $data['appointment_capacity_basis']->value
                : (string) $data['appointment_capacity_basis'],
            'appointment_scheduling_target_percent' => (int) $data['appointment_scheduling_target_percent'],
            'appointment_capacity_enforcement' => $data['appointment_capacity_enforcement'] instanceof \BackedEnum
                ? $data['appointment_capacity_enforcement']->value
                : (string) $data['appointment_capacity_enforcement'],
            'workstation_idle_lock_minutes' => max(0, (int) ($data['workstation_idle_lock_minutes'] ?? $settings->workstation_idle_lock_minutes ?? WorkstationPresenceSettings::DEFAULT_IDLE_LOCK_MINUTES)),
        ];

        $followShopHours = null;
        if ($request->has('scheduling_hours_follow_shop') || $request->exists('scheduling_hours')) {
            $followShopHours = $request->boolean('scheduling_hours_follow_shop', true);
            if ($followShopHours) {
                $payload['scheduling_hours'] = null;
                $effectiveSchedulingHours = \App\Ark\Operations\Appointments\SchedulingHours::fromBusinessHours(
                    \App\Ark\Operations\Telephony\TelephonyCallFlowSettings::fromShopSettings($settings)->weeklyHours(),
                );
            } else {
                $weeklyInput = is_array($request->input('scheduling_hours')) ? $request->input('scheduling_hours') : [];
                $weekly = [];
                foreach (\App\Ark\Operations\Appointments\SchedulingHours::WEEKDAYS as $day) {
                    $dayInput = is_array($weeklyInput[$day] ?? null) ? $weeklyInput[$day] : [];
                    $weekly[$day] = [
                        'enabled' => $request->boolean("scheduling_hours.{$day}.enabled"),
                        'open' => $dayInput['open'] ?? '08:00',
                        'close' => $dayInput['close'] ?? '17:00',
                    ];
                }
                $payload['scheduling_hours'] = \App\Ark\Operations\Appointments\SchedulingHours::normalize($weekly);
                $effectiveSchedulingHours = $payload['scheduling_hours'];
            }
        } else {
            $effectiveSchedulingHours = $settings->schedulingHours();
        }

        $windowWarnings = [];

        if ($request->has('appointment_request_availability')) {
            $weekly = [];
            foreach (\App\Ark\Operations\Appointments\AppointmentRequestAvailability::WEEKDAYS as $day) {
                $weekly[$day] = [
                    'enabled' => $request->boolean("appointment_request_availability.weekly.{$day}.enabled"),
                ];
            }

            $horizonDays = (int) $request->input('appointment_request_availability.horizon_days', 14);
            if ($request->boolean('appointment_request_availability.horizon_is_custom')) {
                $horizonDays = (int) $request->input('appointment_request_availability.horizon_custom_days', $horizonDays);
            }

            $existingWindows = \App\Ark\Operations\Appointments\ScheduleRequestWindows::normalize(
                is_array($settings->appointment_request_availability) ? $settings->appointment_request_availability : null,
            );

            $hasWindowFields = $request->has('appointment_request_availability.request_windows')
                || $request->exists('appointment_request_availability.request_windows.morning.open')
                || $request->exists('appointment_request_availability.request_windows.afternoon.open')
                || $request->exists('appointment_request_availability.request_windows.flexible_enabled')
                || $request->exists('appointment_request_availability.request_windows.latest_appointment_arrival_enabled')
                || $request->exists('appointment_request_availability.request_windows.latest_appointment_arrival');

            $latestEnabled = $request->boolean('appointment_request_availability.request_windows.latest_appointment_arrival_enabled');
            $latestValue = $latestEnabled && $request->filled('appointment_request_availability.request_windows.latest_appointment_arrival')
                ? (string) $request->input('appointment_request_availability.request_windows.latest_appointment_arrival')
                : null;

            $requestWindowsInput = [
                'request_windows' => $hasWindowFields
                    ? [
                        'morning' => [
                            'enabled' => $request->boolean('appointment_request_availability.request_windows.morning.enabled'),
                            'open' => (string) $request->input('appointment_request_availability.request_windows.morning.open', $existingWindows['morning']['open']),
                            'close' => (string) $request->input('appointment_request_availability.request_windows.morning.close', $existingWindows['morning']['close']),
                        ],
                        'afternoon' => [
                            'enabled' => $request->boolean('appointment_request_availability.request_windows.afternoon.enabled'),
                            'open' => (string) $request->input('appointment_request_availability.request_windows.afternoon.open', $existingWindows['afternoon']['open']),
                            'close' => (string) $request->input('appointment_request_availability.request_windows.afternoon.close', $existingWindows['afternoon']['close']),
                        ],
                        'flexible_enabled' => $request->boolean('appointment_request_availability.request_windows.flexible_enabled'),
                        'latest_appointment_arrival' => $request->exists('appointment_request_availability.request_windows.latest_appointment_arrival_enabled')
                            ? $latestValue
                            : $existingWindows['latest_appointment_arrival'],
                    ]
                    : $existingWindows,
            ];

            $normalizedAvailability = \App\Ark\Operations\Appointments\AppointmentRequestAvailability::normalize(
                [
                    'weekly' => $weekly,
                    'horizon_days' => $horizonDays,
                    'minimum_notice_days' => (int) $request->input('appointment_request_availability.minimum_notice_days', 0),
                    ...$requestWindowsInput,
                ],
                $effectiveSchedulingHours,
            );

            $windowMessages = \App\Ark\Operations\Appointments\ScheduleRequestWindows::validationMessages(
                $normalizedAvailability['request_windows'],
                $effectiveSchedulingHours,
            );
            if ($windowMessages !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'appointment_request_availability.request_windows' => $windowMessages[0],
                ]);
            }

            $windowWarnings = \App\Ark\Operations\Appointments\ScheduleRequestWindows::validationWarnings(
                $normalizedAvailability['request_windows'],
                $effectiveSchedulingHours,
            );

            $payload['appointment_request_availability'] = $normalizedAvailability;
        }

        $settings->update($payload);

        $redirect = redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('status', 'Operations settings saved.');

        if ($windowWarnings !== []) {
            $redirect->with('warning', $windowWarnings[0]);
        }

        return $redirect;
    }

    public function applyOperationalProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'operational_profile' => ['required', Rule::enum(OperationalProfile::class)],
        ]);

        $profile = OperationalProfile::from($data['operational_profile']);
        app(ApplyOperationalProfileDefaults::class)->apply($profile);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'operations'])
            ->with('status', $profile->label().' defaults applied. Review appointments, intake, and printing - adjust anything that does not fit this shop.');
    }

public function updateExcellence(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'effective_labor_rate_floor' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'aro_target' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'parts_margin_target_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'labor_sales_target_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'parts_sales_target_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'monthly_fixed_costs' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'net_profit_target_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'income_tax_reserve_percent' => ['required', 'integer', 'min:0', 'max:99'],
            'payroll_tax_reserve_percent' => ['required', 'integer', 'min:0', 'max:99'],
            'monthly_payroll_tax' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'owner_digest_enabled' => ['nullable', 'boolean'],
            'owner_digest_time' => ['required', 'date_format:H:i'],
            'mark_target_reviewed' => ['nullable', 'boolean'],
            'opportunity_ros_per_open_day' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'dollar_close_target_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sold_labor_hours_per_open_day' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'median_cycle_target_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'stalled_ro_age_days' => ['nullable', 'numeric', 'min:0.1', 'max:365'],
            'parts_margin_target_active' => ['nullable', 'boolean'],
        ]);

        $timezone = ShopDisplayTimezone::resolve();
        $lastReview = (bool) ($data['mark_target_reviewed'] ?? false)
            ? now($timezone)->toDateString()
            : ShopExcellenceTargets::lastTargetReview();

        ShopExcellenceTargets::persist([
            'effective_labor_rate_floor_cents' => filled($data['effective_labor_rate_floor'] ?? null)
                ? (int) round(((float) $data['effective_labor_rate_floor']) * 100)
                : null,
            'aro_target_cents' => (int) round(((float) $data['aro_target']) * 100),
            'parts_margin_target_percent' => (int) $data['parts_margin_target_percent'],
            'labor_sales_target_percent' => (int) $data['labor_sales_target_percent'],
            'parts_sales_target_percent' => (int) $data['parts_sales_target_percent'],
            'monthly_fixed_costs_cents' => filled($data['monthly_fixed_costs'] ?? null)
                ? (int) round(((float) $data['monthly_fixed_costs']) * 100)
                : null,
            'net_profit_target_percent' => (int) $data['net_profit_target_percent'],
            'income_tax_reserve_percent' => (int) $data['income_tax_reserve_percent'],
            'payroll_tax_reserve_percent' => (int) $data['payroll_tax_reserve_percent'],
            'monthly_payroll_tax_cents' => filled($data['monthly_payroll_tax'] ?? null)
                ? (int) round(((float) $data['monthly_payroll_tax']) * 100)
                : null,
            'owner_digest_enabled' => (bool) ($data['owner_digest_enabled'] ?? false),
            'owner_digest_time' => (string) $data['owner_digest_time'],
            'last_target_review' => $lastReview,
            'opportunity_ros_per_open_day' => $data['opportunity_ros_per_open_day'] ?? null,
            'dollar_close_target_percent' => $data['dollar_close_target_percent'] ?? null,
            'sold_labor_hours_per_open_day' => $data['sold_labor_hours_per_open_day'] ?? null,
            'median_cycle_target_days' => $data['median_cycle_target_days'] ?? null,
            'stalled_ro_age_days' => $data['stalled_ro_age_days'] ?? null,
            'parts_margin_target_active' => (bool) ($data['parts_margin_target_active'] ?? false),
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'excellence'])
            ->with('status', 'Owner targets saved.');
    }

public function updatePrinting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qz_printing_enabled' => ['nullable', 'boolean'],
            'qz_printing_key_tag_printer' => ['nullable', 'string', 'max:255'],
            'qz_printing_oil_sticker_printer' => ['nullable', 'string', 'max:255'],
            'qz_key_tag_label_width_mm' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'qz_key_tag_label_height_mm' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'qz_key_tag_vin_display' => ['required', 'string', Rule::in(['last6', 'last8', 'full'])],
            'qz_key_tag_media_type' => ['nullable', 'string', Rule::in(['mono', 'red_black'])],
            'qz_key_tag_orientation' => ['nullable', 'string', Rule::in(['auto', 'portrait', 'landscape'])],
            'qz_raster_dpi' => ['nullable', 'integer', Rule::in([203, 300])],
            'oil_change_sticker_next_due_months' => ['required', 'integer', 'min:1', 'max:24'],
            'oil_change_interval_miles' => ['required', 'integer', 'min:1000', 'max:50000'],
        ]);

        ShopSettings::current()->update([
            'qz_printing_enabled' => (bool) ($data['qz_printing_enabled'] ?? false),
            'qz_printing_key_tag_printer' => filled($data['qz_printing_key_tag_printer'] ?? null)
                ? trim((string) $data['qz_printing_key_tag_printer'])
                : null,
            'qz_printing_oil_sticker_printer' => filled($data['qz_printing_oil_sticker_printer'] ?? null)
                ? trim((string) $data['qz_printing_oil_sticker_printer'])
                : null,
            'qz_key_tag_label_width_mm' => filled($data['qz_key_tag_label_width_mm'] ?? null)
                ? $data['qz_key_tag_label_width_mm']
                : null,
            'qz_key_tag_label_height_mm' => filled($data['qz_key_tag_label_height_mm'] ?? null)
                ? $data['qz_key_tag_label_height_mm']
                : null,
            'qz_key_tag_vin_display' => $data['qz_key_tag_vin_display'],
            'qz_key_tag_media_type' => $data['qz_key_tag_media_type'] ?? ShopSettings::current()->qz_key_tag_media_type ?? 'mono',
            'qz_key_tag_orientation' => $data['qz_key_tag_orientation']
                ?? ShopSettings::current()->qz_key_tag_orientation
                ?? config('printing.key_tag_qz_orientation', 'portrait'),
            'qz_raster_dpi' => $data['qz_raster_dpi'] ?? null,
            'oil_change_sticker_next_due_months' => (int) $data['oil_change_sticker_next_due_months'],
            'oil_change_interval_miles' => (int) $data['oil_change_interval_miles'],
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'printing'])
            ->with('status', 'Printing settings saved.');
    }

public function updateOverhead(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'costs' => ['nullable', 'array'],
            'costs.rent' => ['nullable', 'string', 'max:32'],
            'costs.utilities' => ['nullable', 'string', 'max:32'],
            'costs.insurance' => ['nullable', 'string', 'max:32'],
            'costs.software' => ['nullable', 'string', 'max:32'],
            'costs.equipment' => ['nullable', 'string', 'max:32'],
            'costs.office_payroll' => ['nullable', 'string', 'max:32'],
            'costs.other' => ['nullable', 'string', 'max:32'],
            'fixed_cost_lines' => ['nullable', 'array'],
            'fixed_cost_lines.*.label' => ['required', 'string', 'max:120'],
            'fixed_cost_lines.*.amount' => ['nullable', 'string', 'max:32'],
            'fixed_cost_lines.*.period' => ['nullable', 'string', Rule::in(ShopFixedCostPeriod::ALL)],
            'monthly_card_volume' => ['nullable', 'string', 'max:32'],
            'card_processing_percent' => ['nullable', 'string', 'max:32'],
            'merchant_financing_holdback_percent' => ['nullable', 'string', 'max:32'],
            'fixed_monthly_financing_payment' => ['nullable', 'string', 'max:32'],
            'technician_count' => ['nullable', 'string', 'max:32'],
            'workdays_per_month' => ['nullable', 'string', 'max:32'],
            'workday_hours' => ['nullable', 'string', 'max:32'],
            'billable_utilization' => ['nullable', 'string', 'max:32'],
            'overhead_tab' => ['nullable', 'string', Rule::in(['fixed-costs', 'payments', 'capacity'])],
        ]);

        $snapshot = ShopOverheadSnapshot::fromState($data);
        $state = $snapshot->normalizedState();
        $perHour = $snapshot->overheadPerBilledHour();

        ShopSettings::current()->update([
            'shop_overhead_state' => $state,
            'shop_overhead_per_hour_cents' => $perHour !== null ? (int) round($perHour * 100) : null,
        ]);

        $monthlyFixed = $snapshot->monthlyFixedOverheadTotal();

        if ($monthlyFixed > 0) {
            ShopExcellenceTargets::persist(array_merge(ShopExcellenceTargets::current(), [
                'monthly_fixed_costs_cents' => (int) round($monthlyFixed * 100),
            ]));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Shop overhead saved.',
                'overhead_per_hour' => $perHour,
                'monthly_fixed_costs' => $monthlyFixed,
                'state' => $state,
            ]);
        }

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'overhead'])
            ->with('status', 'Shop overhead saved.');
    }
}
