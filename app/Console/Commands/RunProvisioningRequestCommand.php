<?php

namespace App\Console\Commands;

use App\Ark\Platform\Provisioning\ProvisioningOrchestrator;
use App\Ark\Platform\ProvisioningRequest;
use Illuminate\Console\Command;

class RunProvisioningRequestCommand extends Command
{
    protected $signature = 'ark:provisioning:run {id : Provisioning request id}';

    protected $description = 'Run the Provisioning Orchestrator for a request (Sprint 1 stubs succeed)';

    public function handle(ProvisioningOrchestrator $orchestrator): int
    {
        $request = ProvisioningRequest::query()->find($this->argument('id'));

        if ($request === null) {
            $this->error('Provisioning request not found.');

            return self::FAILURE;
        }

        $result = $orchestrator->run($request);

        $this->info("Status: {$result->status->value}");
        $steps = $result->completedStepKeys();
        $this->line('Completed steps: '.($steps === [] ? '(none)' : implode(', ', $steps)));

        if ($result->failure_reason) {
            $this->warn("Failure: {$result->failure_reason}");
        }

        return $result->status->value === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
