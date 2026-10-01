<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Intake\IntakeWorkspaceSession;
use App\Ark\Operations\Leads\LeadContactNameParser;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerStoreController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $customerTypes = collect(ShopSettings::current()->customerTypeRows())
            ->pluck('name')
            ->all();

        $intakeCreate = $request->boolean('intake');

        if ($request->input('contact_preference') === '') {
            $request->merge(['contact_preference' => null]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => [$intakeCreate ? 'nullable' : 'required', 'string', 'max:255'],
            'phone' => [$intakeCreate ? 'required' : 'nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_preference' => ['nullable', Rule::enum(LeadContactPreference::class)],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'referral_source' => ['nullable', Rule::enum(EncounterSource::class)],
            'customer_type' => ['nullable', Rule::in($customerTypes)],
            'notes' => ['nullable', 'string'],
            'intake' => ['nullable', 'boolean'],
        ]);

        $data['customer_type'] ??= $customerTypes[0] ?? 'Retail';
        $data['last_name'] = LeadContactNameParser::normalizeLastName($data['last_name'] ?? null);
        $returnToIntake = $request->boolean('intake');
        $returnToSchedule = $request->input('return_to') === 'schedule';
        unset($data['intake'], $data['return_to']);

        $customer = Customer::query()->create($data);

        if ($returnToSchedule) {
            $scheduleContext = array_filter([
                'customer' => $customer->id,
                'conversation' => $request->integer('schedule_conversation') ?: null,
                'starts_at' => $request->filled('schedule_starts_at') ? (string) $request->string('schedule_starts_at') : null,
                'ends_at' => $request->filled('schedule_ends_at') ? (string) $request->string('schedule_ends_at') : null,
                'duration_minutes' => $request->integer('schedule_duration_minutes') ?: null,
                'technician_user_id' => $request->integer('schedule_technician_user_id') ?: null,
                'workstation_id' => $request->integer('schedule_workstation_id') ?: null,
            ]);

            return redirect()
                ->to(\App\Ark\Operations\Appointments\ScheduleUrl::to($scheduleContext))
                ->with('status', 'Customer added · finish scheduling. Vehicle can wait until they arrive.');
        }

        if ($returnToIntake) {
            return redirect()
                ->to(IntakeWorkspaceSession::routeFromRequestOrInput($request, [
                    'customer_id' => $customer->id,
                ]))
                ->with('status', 'Customer added · '.$customer->name);
        }

        return redirect()->route('operations.customers.show', $customer);
    }
}
