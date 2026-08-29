<?php

namespace App\Ark\Operations\Inspections;

/**
 * Binding product definitions.
 *
 * Standard Vehicle Inspection — Corner Inspection v1.0 frozen in
 * docs/inspection/corner-inspection-v1-freeze.md (Phase 2A).
 * Do not expand Standard into Steering / Under Vehicle / etc. from this class
 * beyond preserving existing non-corner categories until those phases freeze.
 *
 * PPI content remains from templates v1 frozen proposal.
 */
final class FrozenInspectionTemplateDefinitions
{
    /**
     * @return list<array{name: string, items: list<array<string, mixed>>}>
     */
    public static function standard(): array
    {
        $cornerTire = InspectionMeasurementSlots::cornerTire();
        $discPads = InspectionMeasurementSlots::discBrakePadsAndRotor();
        $drum = InspectionMeasurementSlots::drumBrake();

        return [
            ['name' => 'Rear axle', 'items' => [
                self::point(
                    'Rear axle brake type',
                    key: 'rear_axle_brake_type',
                    gateGroup: 'axle_gate',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopRearAxleMeta(),
                ),
            ]],
            ['name' => 'Left Front', 'items' => self::cornerPoints(
                corner: 'lf',
                labelPrefix: 'LF',
                axleRole: 'front',
                tireSlots: $cornerTire,
                discPads: $discPads,
            )],
            ['name' => 'Left Rear', 'items' => self::cornerPoints(
                corner: 'lr',
                labelPrefix: 'LR',
                axleRole: 'rear',
                tireSlots: $cornerTire,
                discPads: $discPads,
                drumSlots: $drum,
            )],
            ['name' => 'Right Rear', 'items' => self::cornerPoints(
                corner: 'rr',
                labelPrefix: 'RR',
                axleRole: 'rear',
                tireSlots: $cornerTire,
                discPads: $discPads,
                drumSlots: $drum,
            )],
            ['name' => 'Right Front', 'items' => self::cornerPoints(
                corner: 'rf',
                labelPrefix: 'RF',
                axleRole: 'front',
                tireSlots: $cornerTire,
                discPads: $discPads,
            )],
            ['name' => 'Brake system', 'items' => [
                self::point(
                    'Brake fluid — level / condition',
                    key: 'std_brake_fluid',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeasureMeta('shared', 'brake_fluid'),
                ),
                self::point(
                    'Parking brake',
                    key: 'std_parking_brake',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeasureMeta('shared', 'parking_brake'),
                ),
            ]],
            // Preserved until later phase freezes — not redesigned in Phase 2A.
            ['name' => 'Arrival / outside', 'items' => [
                self::point('Warning lights / dash indicators', key: 'std_warning_lights'),
                self::point('Exterior lights (head / brake / turn / plate)', key: 'std_exterior_lights'),
                self::point('Wipers / washer', key: 'std_wipers'),
                self::point('Obvious body / underbody damage (ground view)', key: 'std_body_damage'),
            ]],
            ['name' => 'Under hood', 'items' => [
                self::point('Engine oil level / condition', key: 'std_oil'),
                self::point('Coolant level / condition', key: 'std_coolant'),
                self::point('Battery / terminals (visual)', key: 'std_battery_visual'),
                self::point('Belts / obvious hose concerns', key: 'std_belts_hoses'),
                self::point('Air filter', key: 'std_air_filter'),
                self::point('Visible under-hood leaks', key: 'std_hood_leaks'),
            ]],
            ['name' => 'Under vehicle', 'items' => [
                self::point('Fluid leaks (underbody)', key: 'std_underbody_leaks'),
                self::point('Steering linkage / tie rods', key: 'std_steering_linkage'),
                self::point('Ball joints / obvious joint play', key: 'std_ball_joints'),
                self::point('Front struts/shocks — leaks or damage', key: 'std_front_struts'),
                self::point('Rear shocks/struts — leaks or damage', key: 'std_rear_shocks'),
                self::point('Rear bushings / components — obvious looseness or damage', key: 'std_rear_bushings'),
                self::point('CV boots / driveline visual', key: 'std_cv_boots'),
                self::point('Exhaust condition', key: 'std_exhaust'),
            ]],
            ['name' => 'Road / operational', 'items' => [
                self::point('Road test performed', key: 'std_road_test_performed', gateGroup: 'road_test_performed'),
                self::point('Road-test noise / vibration / drivability observation', key: 'std_road_test_observation', gateGroup: 'road_test_findings'),
            ]],
        ];
    }

    /**
     * @return list<array{name: string, items: list<array<string, mixed>>}>
     */
    public static function prePurchase(): array
    {
        $tread = InspectionMeasurementSlots::treadThreeZone();
        $psi = InspectionMeasurementSlots::tirePsiFourCorner();
        $disc = InspectionMeasurementSlots::discBrakePadsAndRotor();
        $drum = InspectionMeasurementSlots::drumBrake();
        $batteryTest = [
            ['key' => 'voltage', 'name' => 'Voltage', 'unit' => 'V', 'required' => true, 'type' => 'number'],
            ['key' => 'cca_or_result', 'name' => 'CCA / test result', 'unit' => null, 'required' => true, 'type' => 'text'],
        ];
        $chargingTest = [
            ['key' => 'charging_voltage', 'name' => 'Charging voltage', 'unit' => 'V', 'required' => true, 'type' => 'number'],
            ['key' => 'charging_result', 'name' => 'Test result', 'unit' => null, 'required' => true, 'type' => 'text'],
        ];

        return [
            ['name' => 'Exterior', 'items' => [
                self::point('Body panels — dents / prior repair clues', key: 'ppi_body'),
                self::point('Paint / clearcoat condition', key: 'ppi_paint'),
                self::point('Rust / corrosion (visible exterior)', key: 'ppi_rust'),
                self::point('Glass / mirrors', key: 'ppi_glass'),
                self::point('Headlights function', key: 'ppi_headlights'),
                self::point('Brake lights function', key: 'ppi_brake_lights'),
                self::point('Turn signals / hazards function', key: 'ppi_turn_signals'),
                self::point('License plate / marker lights', key: 'ppi_plate_lights'),
                self::point('Wipers', key: 'ppi_wipers'),
                self::point('Washer', key: 'ppi_washer'),
                self::point('Horn', key: 'ppi_horn'),
            ]],
            ['name' => 'Cabin / HVAC / accessories', 'items' => [
                self::point('Seats — condition / operation', key: 'ppi_seats'),
                self::point('Seat belts', key: 'ppi_belts'),
                self::point('Airbag / SRS warning state', key: 'ppi_srs'),
                self::point('Dash warning lamps (key-on)', key: 'ppi_dash_warn'),
                self::point('HVAC blower operation', key: 'ppi_blower'),
                self::point('A/C cool (as applicable)', key: 'ppi_ac'),
                self::point('Heat (as applicable)', key: 'ppi_heat'),
                self::point('Power windows', key: 'ppi_windows'),
                self::point('Power locks', key: 'ppi_locks'),
                self::point('Power mirrors', key: 'ppi_mirrors'),
                self::point('Odor / moisture / water intrusion clues', key: 'ppi_odor'),
            ]],
            ['name' => 'Scan / readiness', 'items' => [
                self::point('Scan — stored codes', key: 'ppi_scan_stored', scanEvidence: true),
                self::point('Scan — pending codes', key: 'ppi_scan_pending', scanEvidence: true),
                self::point('Scan — permanent codes (where supported)', key: 'ppi_scan_permanent', scanEvidence: true),
                self::point('Emissions readiness / monitors', key: 'ppi_readiness', scanEvidence: true),
            ]],
            ['name' => 'Tires / wheels', 'items' => [
                self::point('LF tire condition / damage', key: 'ppi_lf_tire', slots: $tread),
                self::point('RF tire condition / damage', key: 'ppi_rf_tire', slots: $tread),
                self::point('LR tire condition / damage', key: 'ppi_lr_tire', slots: $tread),
                self::point('RR tire condition / damage', key: 'ppi_rr_tire', slots: $tread),
                self::point('Tire pressure', key: 'ppi_tire_pressure', slots: $psi),
                self::point('Tire age / DOT date (if readable)', key: 'ppi_tire_age'),
                self::point('Wheel condition / curb damage', key: 'ppi_wheels'),
                self::point('Spare / inflate kit', key: 'ppi_spare'),
            ]],
            ['name' => 'Under hood', 'items' => [
                self::point('Engine oil level / condition', key: 'ppi_oil'),
                self::point('Coolant level / condition', key: 'ppi_coolant'),
                self::point('Brake fluid — level / condition', key: 'ppi_brake_fluid'),
                self::point('Power steering fluid (if equipped)', key: 'ppi_ps_fluid'),
                self::point('Washer fluid', key: 'ppi_washer_fluid'),
                self::point('Transmission fluid (dipstick) / sealed note', key: 'ppi_trans_fluid'),
                self::point('Battery terminals / physical condition', key: 'ppi_battery_physical'),
                self::point('Battery test — voltage / CCA or tool result', key: 'ppi_battery_test', slots: $batteryTest),
                self::point('Charging system test — charging voltage / result', key: 'ppi_charging_test', slots: $chargingTest),
                self::point('Belts', key: 'ppi_belts'),
                self::point('Hoses', key: 'ppi_hoses'),
                self::point('Air filter', key: 'ppi_air_filter'),
                self::point('Cabin filter (if accessible)', key: 'ppi_cabin_filter'),
                self::point('Visible under-hood leaks / residue', key: 'ppi_hood_leaks'),
                self::point('Engine / transmission mounts (visual)', key: 'ppi_mounts'),
            ]],
            ['name' => 'Underbody / driveline', 'items' => [
                self::point('Fluid leaks map (underbody)', key: 'ppi_leak_map'),
                self::point('Steering linkage / tie rods', key: 'ppi_steering'),
                self::point('Front ball joints', key: 'ppi_front_bj'),
                self::point('Front control arms / bushings', key: 'ppi_front_arms'),
                self::point('Front struts/shocks', key: 'ppi_front_struts'),
                self::point('Rear shocks/struts', key: 'ppi_rear_shocks'),
                self::point('Rear control arms / bushings / trailing components', key: 'ppi_rear_arms'),
                self::point('Sway bar links / bushings (visual)', key: 'ppi_sway'),
                self::point('Wheel bearings — play / noise', key: 'ppi_bearings'),
                self::point('CV axles / boots', key: 'ppi_cv'),
                self::point('Driveshaft / U-joints (if RWD/AWD as equipped)', key: 'ppi_driveshaft'),
                self::point('Exhaust / heat shields', key: 'ppi_exhaust'),
                self::point('Transmission / transfer case seepage', key: 'ppi_trans_seep'),
                self::point('Differential seepage (if equipped)', key: 'ppi_diff_seep'),
                self::point('Frame / unibody / underbody rust or impact', key: 'ppi_frame'),
                self::point('Brake lines / hoses', key: 'ppi_brake_lines'),
            ]],
            ['name' => 'Brakes', 'items' => [
                self::point('Rear axle brake type', key: 'rear_axle_brake_type', gateGroup: 'axle_gate'),
                self::point('LF brake', key: 'ppi_lf_brake', slots: $disc, axleRole: 'front'),
                self::point('RF brake', key: 'ppi_rf_brake', slots: $disc, axleRole: 'front'),
                self::point('LR brake (disc)', key: 'ppi_lr_brake_disc', slots: $disc, axleRole: 'rear_disc'),
                self::point('RR brake (disc)', key: 'ppi_rr_brake_disc', slots: $disc, axleRole: 'rear_disc'),
                self::point('LR drum brake', key: 'ppi_lr_brake_drum', slots: $drum, axleRole: 'rear_drum'),
                self::point('RR drum brake', key: 'ppi_rr_brake_drum', slots: $drum, axleRole: 'rear_drum'),
                self::point('Parking brake function', key: 'ppi_parking_brake'),
            ]],
            ['name' => 'Road test', 'items' => [
                self::point('Road test performed', key: 'ppi_road_test_performed', gateGroup: 'road_test_performed'),
                self::point('Start / idle / abnormal noises', key: 'ppi_rt_idle', gateGroup: 'road_test_findings'),
                self::point('Acceleration / hesitation', key: 'ppi_rt_accel', gateGroup: 'road_test_findings'),
                self::point('Transmission shift quality (as equipped)', key: 'ppi_rt_shift', gateGroup: 'road_test_findings'),
                self::point('Brake performance / pull', key: 'ppi_rt_brakes', gateGroup: 'road_test_findings'),
                self::point('Steering pull / wander', key: 'ppi_rt_steer', gateGroup: 'road_test_findings'),
                self::point('Vibration / NVH', key: 'ppi_rt_nvh', gateGroup: 'road_test_findings'),
                self::point('Cruise / ABS event if triggered (observe only)', key: 'ppi_rt_abs', gateGroup: 'road_test_findings'),
                self::point('Post-drive leak recheck', key: 'ppi_rt_leaks', gateGroup: 'road_test_findings'),
            ]],
            ['name' => 'Maintenance evidence', 'items' => [
                self::point('Service history clues / stickers / visible evidence', key: 'ppi_service_clues'),
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tireSlots
     * @param  list<array<string, mixed>>  $discPads
     * @param  list<array<string, mixed>>|null  $drumSlots
     * @return list<array<string, mixed>>
     */
    private static function cornerPoints(
        string $corner,
        string $labelPrefix,
        string $axleRole,
        array $tireSlots,
        array $discPads,
        ?array $drumSlots = null,
    ): array {
        // Tires and brakes exist on every vehicle — never offer N/A.
        $items = [
            self::point(
                "{$labelPrefix} Tire",
                key: "std_{$corner}_tire",
                slots: $tireSlots,
                allowsNa: false,
                meta: InspectionTemplatePointMeta::shopCornerMeta('tire', $corner, 'tire'),
            ),
            self::point(
                "{$labelPrefix} Wheel",
                key: "std_{$corner}_wheel",
                allowsNa: false,
                meta: InspectionTemplatePointMeta::shopCornerMeta('wheel', $corner, 'wheel'),
            ),
        ];

        if ($axleRole === 'front') {
            $items = [
                ...$items,
                self::point(
                    "{$labelPrefix} Brake pads",
                    key: "std_{$corner}_brake_pads",
                    slots: $discPads,
                    axleRole: 'front',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeasureMeta($corner, 'brake_assembly'),
                ),
                self::point(
                    "{$labelPrefix} Rotor",
                    key: "std_{$corner}_rotor",
                    axleRole: 'front',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeta('rotor', $corner, 'brake_assembly'),
                ),
                self::point(
                    "{$labelPrefix} Caliper",
                    key: "std_{$corner}_caliper",
                    axleRole: 'front',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeta('caliper', $corner, 'brake_assembly'),
                ),
            ];
        } else {
            $items = [
                ...$items,
                self::point(
                    "{$labelPrefix} Brake pads",
                    key: "std_{$corner}_brake_pads",
                    slots: $discPads,
                    axleRole: 'rear_disc',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeasureMeta($corner, 'brake_assembly'),
                ),
                self::point(
                    "{$labelPrefix} Rotor",
                    key: "std_{$corner}_rotor",
                    axleRole: 'rear_disc',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeta('rotor', $corner, 'brake_assembly'),
                ),
                self::point(
                    "{$labelPrefix} Caliper",
                    key: "std_{$corner}_caliper",
                    axleRole: 'rear_disc',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeta('caliper', $corner, 'brake_assembly'),
                ),
                self::point(
                    "{$labelPrefix} Drum brake",
                    key: "std_{$corner}_brake_drum",
                    slots: $drumSlots ?? InspectionMeasurementSlots::drumBrake(),
                    axleRole: 'rear_drum',
                    allowsNa: false,
                    meta: InspectionTemplatePointMeta::shopCornerMeasureMeta($corner, 'brake_assembly'),
                ),
            ];
        }

        $items[] = self::point(
            "{$labelPrefix} Brake hose",
            key: "std_{$corner}_brake_hose",
            allowsNa: false,
            meta: InspectionTemplatePointMeta::shopCornerMeta('brake_hose', $corner, 'brake_hose'),
        );

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>|null  $slots
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>
     */
    private static function point(
        string $label,
        string $key,
        ?array $slots = null,
        ?string $axleRole = null,
        ?string $gateGroup = null,
        bool $scanEvidence = false,
        bool $allowsNa = true,
        ?array $meta = null,
    ): array {
        return [
            'label' => $label,
            'point_key' => $key,
            'measurement_slots' => $slots,
            'axle_role' => $axleRole,
            'gate_group' => $gateGroup,
            'requires_scan_evidence' => $scanEvidence,
            'requires_photo' => $scanEvidence,
            'allows_na' => $allowsNa,
            'builder_meta' => $meta,
        ];
    }
}
