<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves which /book presentation to render. Recognition never mutates authority.
 *
 * Identity precedes scheduling: guests and returning customers both SMS-verify first.
 */
final class PublicBookExperienceMode
{
    public const IDENTITY = 'identity';

    /** @deprecated Use IDENTITY — kept for any stale view checks during deploy. */
    public const ACQUAINTANCE = self::IDENTITY;

    public const GUEST = 'guest';

    public const VEHICLE_PICK = 'vehicle_pick';

    public const VEHICLE_HOME = 'vehicle_home';

    public const SCHEDULE = 'schedule';

    public function __construct(
        private readonly CustomerRecognitionProjection $recognition,
        private readonly LeadPhoneVerification $phoneVerification,
        private readonly LeadEmailVerification $emailVerification,
    ) {}

    /**
     * @return array{
     *     mode: string,
     *     recognition: ?array,
     *     vehicle_home: ?array,
     *     schedule_vehicle_id: ?int,
     *     schedule_radar_ids: list<int>,
     *     schedule_intent: string|null,
     *     schedule_intent_details: string,
     *     recognized: bool,
     *     verified_phone: string|null,
     *     verified_email: string|null,
     *     identity_gate_ready: bool,
     * }
     */
    public function resolve(Request $request): array
    {
        $customer = Auth::guard('portal')->user();

        if ($customer instanceof Customer) {
            $request->session()->forget(CustomerRecognitionProjection::SESSION_GUEST_KEY);

            return $this->withMeta(
                $this->resolveRecognized($request, $customer),
                verifiedPhone: PhoneNumber::normalize((string) ($customer->phone ?? '')),
                verifiedEmail: filled($customer->email) ? strtolower(trim((string) $customer->email)) : null,
            );
        }

        $verifiedPhone = $this->phoneVerification->verifiedPhone($request->session());
        $verifiedEmail = $this->emailVerification->verifiedEmail($request->session());
        $gateReady = $this->phoneVerification->bookIdentityGateReady()
            || $this->emailVerification->ready();
        $hasIdentityProof = $verifiedPhone !== null || $verifiedEmail !== null;

        // No unauthenticated guest escape — verify first, then guest or recognition.
        if ($hasIdentityProof && $request->session()->get(CustomerRecognitionProjection::SESSION_GUEST_KEY) === true) {
            return $this->withMeta(
                $this->empty(self::GUEST),
                verifiedPhone: $verifiedPhone,
                verifiedEmail: $verifiedEmail,
                identityGateReady: $gateReady,
            );
        }

        if ($hasIdentityProof) {
            // Verified but not yet routed (refresh mid-flight) — continue as guest.
            $request->session()->put(CustomerRecognitionProjection::SESSION_GUEST_KEY, true);

            return $this->withMeta(
                $this->empty(self::GUEST),
                verifiedPhone: $verifiedPhone,
                verifiedEmail: $verifiedEmail,
                identityGateReady: $gateReady,
            );
        }

        return $this->withMeta(
            $this->empty(self::IDENTITY),
            verifiedPhone: null,
            verifiedEmail: null,
            identityGateReady: $gateReady,
        );
    }

    /**
     * @param  array{
     *     mode: string,
     *     recognition: ?array,
     *     vehicle_home: ?array,
     *     schedule_vehicle_id: ?int,
     *     schedule_radar_ids: list<int>,
     *     schedule_intent: string|null,
     *     schedule_intent_details: string,
     *     recognized: bool,
     * }  $base
     * @return array{
     *     mode: string,
     *     recognition: ?array,
     *     vehicle_home: ?array,
     *     schedule_vehicle_id: ?int,
     *     schedule_radar_ids: list<int>,
     *     schedule_intent: string|null,
     *     schedule_intent_details: string,
     *     recognized: bool,
     *     verified_phone: string|null,
     *     verified_email: string|null,
     *     identity_gate_ready: bool,
     * }
     */
    private function withMeta(
        array $base,
        ?string $verifiedPhone = null,
        ?string $verifiedEmail = null,
        ?bool $identityGateReady = null,
    ): array {
        return [
            ...$base,
            'verified_phone' => $verifiedPhone,
            'verified_email' => $verifiedEmail,
            'identity_gate_ready' => $identityGateReady ?? (
                $this->phoneVerification->bookIdentityGateReady() || $this->emailVerification->ready()
            ),
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     recognition: ?array,
     *     vehicle_home: ?array,
     *     schedule_vehicle_id: ?int,
     *     schedule_radar_ids: list<int>,
     *     schedule_intent: string|null,
     *     schedule_intent_details: string,
     *     recognized: bool,
     * }
     */
    private function resolveRecognized(Request $request, Customer $customer): array
    {
        $recognition = $this->recognition->forCustomer($customer);
        $radarIds = $this->radarIdsFromRequest($request);
        $intent = $this->intentFromRequest($request);
        $intentDetails = trim((string) $request->query('intent_details', ''));

        $vehicleId = $request->query('vehicle');
        $vehicle = $this->recognition->resolveOwnedVehicle($customer, $vehicleId);

        if ($request->boolean('schedule')) {
            if ($vehicle instanceof Vehicle) {
                return [
                    'mode' => self::SCHEDULE,
                    'recognition' => $recognition,
                    'vehicle_home' => $this->recognition->forVehicle($customer, $vehicle),
                    'schedule_vehicle_id' => (int) $vehicle->id,
                    'schedule_radar_ids' => $radarIds,
                    'schedule_intent' => $intent,
                    'schedule_intent_details' => $intentDetails,
                    'recognized' => true,
                ];
            }

            return [
                'mode' => self::SCHEDULE,
                'recognition' => $recognition,
                'vehicle_home' => null,
                'schedule_vehicle_id' => null,
                'schedule_radar_ids' => $radarIds,
                'schedule_intent' => $intent,
                'schedule_intent_details' => $intentDetails,
                'recognized' => true,
            ];
        }

        if ($vehicle instanceof Vehicle) {
            return [
                'mode' => self::VEHICLE_HOME,
                'recognition' => $recognition,
                'vehicle_home' => $this->recognition->forVehicle($customer, $vehicle),
                'schedule_vehicle_id' => null,
                'schedule_radar_ids' => [],
                'schedule_intent' => null,
                'schedule_intent_details' => '',
                'recognized' => true,
            ];
        }

        $vehicles = $recognition['vehicles'];

        if (count($vehicles) === 1) {
            $only = $this->recognition->resolveOwnedVehicle($customer, $vehicles[0]['id']);

            if ($only instanceof Vehicle) {
                return [
                    'mode' => self::VEHICLE_HOME,
                    'recognition' => $recognition,
                    'vehicle_home' => $this->recognition->forVehicle($customer, $only),
                    'schedule_vehicle_id' => null,
                    'schedule_radar_ids' => [],
                    'schedule_intent' => null,
                    'schedule_intent_details' => '',
                    'recognized' => true,
                ];
            }
        }

        return [
            'mode' => self::VEHICLE_PICK,
            'recognition' => $recognition,
            'vehicle_home' => null,
            'schedule_vehicle_id' => null,
            'schedule_radar_ids' => [],
            'schedule_intent' => null,
            'schedule_intent_details' => '',
            'recognized' => true,
        ];
    }

    /**
     * @return list<int>
     */
    private function radarIdsFromRequest(Request $request): array
    {
        $raw = $request->query('radar', []);

        if (! is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];

        foreach ($raw as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);

            if ($id !== false && $id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function intentFromRequest(Request $request): ?string
    {
        $intent = trim((string) $request->query('intent', ''));

        if ($intent === '') {
            return null;
        }

        foreach (PublicBookWizardConcerns::conciergeIntents() as $allowed) {
            if (strcasecmp($intent, $allowed) === 0) {
                return $allowed;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     mode: string,
     *     recognition: ?array,
     *     vehicle_home: ?array,
     *     schedule_vehicle_id: ?int,
     *     schedule_radar_ids: list<int>,
     *     schedule_intent: string|null,
     *     schedule_intent_details: string,
     *     recognized: bool,
     * }
     */
    private function empty(string $mode): array
    {
        return [
            'mode' => $mode,
            'recognition' => null,
            'vehicle_home' => null,
            'schedule_vehicle_id' => null,
            'schedule_radar_ids' => [],
            'schedule_intent' => null,
            'schedule_intent_details' => '',
            'recognized' => false,
        ];
    }
}
