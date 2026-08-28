<?php

namespace App\Ark\Operations\Work;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvisorFollowUpCompleteController
{
    use RedirectsAdvisorWorkActions;

    public function __invoke(Request $request, AdvisorFollowUp $followUp): RedirectResponse
    {
        if ($followUp->completed_at === null) {
            $followUp->forceFill(['completed_at' => now()])->save();
        }

        return $this->redirectAfterWorkAction($request, 'Follow-up marked complete.');
    }
}
