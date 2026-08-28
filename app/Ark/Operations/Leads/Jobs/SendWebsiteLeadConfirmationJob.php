<?php

namespace App\Ark\Operations\Leads\Jobs;

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\SendWebsiteLeadConfirmationAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWebsiteLeadConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
    ) {}

    public function handle(SendWebsiteLeadConfirmationAction $confirmation): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $confirmation->execute($lead);
    }
}
