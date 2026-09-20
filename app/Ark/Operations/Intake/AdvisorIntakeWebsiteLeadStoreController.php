<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvisorIntakeWebsiteLeadStoreController
{
    public function __construct(
        private readonly LeadRecorder $leads,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'concern' => ['required', 'string', 'max:5000'],
            'callback_name' => ['nullable', 'string', 'max:255'],
            'callback_phone' => ['nullable', 'string', 'max:32'],
            'rough_vehicle' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::enum(EncounterSource::class)],
            'visit_mode' => ['nullable', Rule::enum(RepairOrderVisitMode::class)],
            'appointment' => ['nullable', 'boolean'],
        ]);

        $source = EncounterSource::from($validated['source']);

        $lead = null;

        if ($source === EncounterSource::Website) {
            $lead = $this->leads->recordWebsiteSubmission([
                'concern' => trim($validated['concern']),
                'contact_phone' => $validated['callback_phone'] ?? '',
                'contact_name' => $validated['callback_name'] ?? null,
                'source' => LeadSource::Website,
                'metadata' => filled($validated['rough_vehicle'] ?? null)
                    ? ['rough_vehicle' => trim((string) $validated['rough_vehicle'])]
                    : null,
            ], $request->user());
        }

        $params = $lead !== null
            ? IntakeEntryQuery::fromLead($lead)
            : IntakeEntryQuery::fromWebsiteLead($validated);

        return redirect()
            ->route('operations.intake.create', $params)
            ->with('status', $source === EncounterSource::Website
                ? 'Website lead logged — finish intake to open a repair order.'
                : 'Continue in intake to open a repair order.');
    }
}
