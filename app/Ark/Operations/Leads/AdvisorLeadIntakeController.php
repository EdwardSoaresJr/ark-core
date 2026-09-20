<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Intake\IntakeEntryQuery;
use Illuminate\Http\RedirectResponse;

class AdvisorLeadIntakeController
{
    public function __invoke(Lead $lead): RedirectResponse
    {
        return redirect()->route('operations.intake.create', IntakeEntryQuery::fromLead($lead));
    }
}
