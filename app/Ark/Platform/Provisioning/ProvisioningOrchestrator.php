<?php

namespace App\Ark\Platform\Provisioning;

use App\Ark\Platform\Provisioning\Coolify\CoolifyAdapter;
use App\Ark\Platform\Provisioning\Events\ProvisioningCompleted;
use App\Ark\Platform\Provisioning\Events\ProvisioningFailed;
use App\Ark\Platform\Provisioning\Events\ProvisioningStarted;
use App\Ark\Platform\Provisioning\Events\ProvisioningStepCompleted;
use App\Ark\Platform\Provisioning\Events\ProvisioningStepFailed;
use App\Ark\Platform\Provisioning\Events\ProvisioningStepStarted;
use App\Ark\Platform\Provisioning\Steps\StubBootstrapStep;
use App\Ark\Platform\Provisioning\Steps\StubDnsStep;
use App\Ark\Platform\Provisioning\Steps\StubEmailStep;
use App\Ark\Platform\Provisioning\Steps\StubStanclStep;
use App\Ark\Platform\ProvisioningRequest;
use App\Ark\Platform\ProvisioningRequestStatus;
use InvalidArgumentException;
use Throwable;

/**
 * Coordinates steps only — never performs infrastructure work.
 *
 * @see docs/platform/orchestrator-rule-v1.md
 * @see docs/platform/adapter-rule-v1.md
 */
final class ProvisioningOrchestrator
{
    /**
     * @param  list<ProvisioningStep>|null  $steps
     */
    public function __construct(
        private readonly ?array $steps = null,
    ) {}

    public function run(ProvisioningRequest $request): ProvisioningRequest
    {
        if (in_array($request->status, [ProvisioningRequestStatus::Completed, ProvisioningRequestStatus::Cancelled], true)) {
            throw new InvalidArgumentException(
                "Provisioning request {$request->id} is {$request->status->value} and cannot be run.",
            );
        }

        $request->forceFill([
            'status' => ProvisioningRequestStatus::Running,
            'started_at' => $request->started_at ?? now(),
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        event(new ProvisioningStarted($request->fresh()));

        $completed = $request->completedStepKeys();

        try {
            foreach ($this->steps() as $step) {
                if (in_array($step->key(), $completed, true)) {
                    continue;
                }

                $fresh = $request->fresh();
                event(new ProvisioningStepStarted($fresh, $step->key()));

                $result = $step->execute($fresh);

                if (! $result->succeeded) {
                    $reason = $result->failureReason ?? "Step {$step->key()} failed.";

                    $request->forceFill([
                        'status' => ProvisioningRequestStatus::Failed,
                        'failed_at' => now(),
                        'failure_reason' => $reason,
                    ])->save();

                    $failed = $request->fresh();
                    event(new ProvisioningStepFailed($failed, $step->key(), $reason));
                    event(new ProvisioningFailed($failed, $reason));

                    return $failed;
                }

                $completed[] = $step->key();
                $request->forceFill([
                    'completed_steps' => array_values(array_unique($completed)),
                ])->save();

                event(new ProvisioningStepCompleted($request->fresh(), $step->key()));
            }
        } catch (Throwable $e) {
            $request->forceFill([
                'status' => ProvisioningRequestStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ])->save();

            $failed = $request->fresh();
            event(new ProvisioningFailed($failed, $e->getMessage()));

            return $failed;
        }

        $request->forceFill([
            'status' => ProvisioningRequestStatus::Completed,
            'completed_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        $done = $request->fresh();
        event(new ProvisioningCompleted($done));

        return $done;
    }

    /**
     * @return list<ProvisioningStep>
     */
    private function steps(): array
    {
        return $this->steps ?? [
            app(CoolifyAdapter::class),
            new StubStanclStep,
            new StubDnsStep,
            new StubBootstrapStep,
            new StubEmailStep,
        ];
    }
}
