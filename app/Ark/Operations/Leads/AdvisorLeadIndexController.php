<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use Illuminate\Http\RedirectResponse;

/**
 * Leads index retired — pre-RO opportunities are worked from Communications Needs Attention.
 * Lead authority (intake, state, create-contact) remains.
 */
class AdvisorLeadIndexController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->to(CommunicationsNeedsYou::url());
    }
}
