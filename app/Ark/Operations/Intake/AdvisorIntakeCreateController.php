<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Intake\IntakeWorkspaceSession;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactNameParser;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\PriorVisitMentionProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderMention;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvisorIntakeCreateController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($redirect = IntakeWorkspaceSession::ensureOnRequest($request)) {
            return $redirect;
        }

        $searchQuery = trim((string) $request->query('q', ''));
        $prefillPhone = $this->prefillPhoneFromRequest($request);
        $lead = $request->integer('lead_id')
            ? Lead::query()->find($request->integer('lead_id'))
            : null;

        if ($prefillPhone === '' && $lead !== null && filled($lead->contact_phone)) {
            $prefillPhone = PhoneNumber::display((string) $lead->contact_phone)
                ?? (string) $lead->contact_phone;
        }

        if ($lead !== null && $searchQuery === '' && filled($lead->contact_name)) {
            $searchQuery = trim((string) $lead->contact_name);
        }

        $customerId = $request->integer('customer_id') ?: null;
        $vehicleId = $request->integer('vehicle_id') ?: null;
        $forceVehicleStep = $request->boolean('select_vehicle');

        $customer = $customerId
            ? Customer::query()->with([
                'vehicles' => fn ($q) => $q->orderByDesc('id'),
                'vehicles.repairOrders' => fn ($q) => $q
                    ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                    ->latest()
                    ->limit(1),
            ])->find($customerId)
            : null;

        $searchCustomers = $searchQuery !== '' && $customer === null
            ? CustomerSearchQuery::matching($searchQuery)
            : collect();

        $intakeStep = 'customer';
        $selectedVehicle = null;
        $lastVisitVehicle = null;

        if ($customer) {
            if ($forceVehicleStep) {
                $intakeStep = 'vehicle';
                $selectedVehicle = null;
                $vehicleId = null;
            } elseif ($vehicleId) {
                $selectedVehicle = $customer->vehicles->firstWhere('id', $vehicleId);

                if ($selectedVehicle !== null) {
                    $intakeStep = 'open';
                } else {
                    $vehicleId = null;
                    $intakeStep = 'vehicle';
                }
            } else {
                $intakeStep = 'vehicle';
            }

            if ($intakeStep === 'vehicle' && $customer->vehicles->count() > 1) {
                $lastVisitVehicle = $this->lastVisitVehicleFor($customer);
            }
        }

        $initialVisitReason = trim((string) old('visit_reason', ''));

        if ($initialVisitReason === '') {
            $initialVisitReason = trim((string) $request->query('concern', ''));
        }

        if ($initialVisitReason === '' && $lead !== null) {
            $initialVisitReason = trim((string) $lead->concern);
        }

        $sourceRepairOrder = $customer !== null && $selectedVehicle !== null && $request->integer('source_repair_order_id')
            ? RepairOrder::query()
                ->whereKey($request->integer('source_repair_order_id'))
                ->where('customer_id', $customer->id)
                ->where('vehicle_id', $selectedVehicle->id)
                ->first()
            : null;

        if ($sourceRepairOrder !== null && (int) $sourceRepairOrder->repair_order_id > 0) {
            $sourceNumber = (int) $sourceRepairOrder->repair_order_id;

            if (! in_array($sourceNumber, RepairOrderMention::numbersIn($initialVisitReason), true)) {
                $sourceReference = 'Previous RO: '.RepairOrderMention::token($sourceNumber);
                $initialVisitReason = trim(implode("\n", array_filter([
                    $initialVisitReason,
                    $sourceReference,
                ])));
            }
        }

        $leadContactNames = $lead !== null
            ? LeadContactNameParser::split($lead->contact_name)
            : ['first_name' => '', 'last_name' => ''];
        $leadLastNameOptional = LeadContactNameParser::allowsOptionalLastName($lead);
        $leadReferralSource = $lead?->source === LeadSource::Website
            ? EncounterSource::Website->value
            : '';

        return view('operations.intake.create', [
            'intakeWorkspaceParams' => IntakeWorkspaceSession::paramsFromRequest($request),
            'searchQuery' => $searchQuery,
            'prefillPhone' => $prefillPhone,
            'searchCustomers' => $searchCustomers,
            'customer' => $customer,
            'selectedVehicleId' => $vehicleId,
            'selectedVehicle' => $selectedVehicle,
            'intakeStep' => $intakeStep,
            'customerTypes' => ShopSettings::current()->customerTypeRows(),
            'referralSources' => EncounterSource::options(),
            'initialVisitReason' => $initialVisitReason,
            'priorVisitMentions' => PriorVisitMentionProjection::for(
                $customer?->id,
                null,
                $selectedVehicle?->id,
            ),
            'lastVisitVehicle' => $lastVisitVehicle,
            'lead' => $lead,
            'leadContactNames' => $leadContactNames,
            'leadLastNameOptional' => $leadLastNameOptional,
            'leadReferralSource' => $leadReferralSource,
        ]);
    }

    private function prefillPhoneFromRequest(Request $request): string
    {
        if ($request->integer('customer_id')) {
            return '';
        }

        $phone = trim((string) $request->query('phone', ''));

        if ($phone === '') {
            return '';
        }

        $normalized = PhoneNumber::normalize($phone);

        return PhoneNumber::display($normalized) ?? $phone;
    }

    private function lastVisitVehicleFor(Customer $customer): ?Vehicle
    {
        $lastVehicleId = RepairOrder::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('vehicle_id')
            ->latest('id')
            ->value('vehicle_id');

        if ($lastVehicleId === null) {
            return null;
        }

        return $customer->vehicles->firstWhere('id', $lastVehicleId);
    }
}
