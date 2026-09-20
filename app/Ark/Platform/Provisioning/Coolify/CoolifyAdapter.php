<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;
use Throwable;

/**
 * ProvisioningStep: ProvisioningRequest → Coolify deploy progress.
 *
 * @see docs/platform/sprint-2-coolify-adapter.md
 */
final class CoolifyAdapter implements ProvisioningStep
{
    public function __construct(
        private readonly CoolifyClient $client,
        private readonly CoolifyDeploymentMapper $mapper = new CoolifyDeploymentMapper,
        private readonly CoolifyExecutionStore $execution = new CoolifyExecutionStore,
        private readonly int $milestone = 1,
        private readonly ?string $applicationUuid = null,
        private readonly bool $enabled = true,
        private readonly int $pollIntervalSeconds = 2,
        private readonly int $pollTimeoutSeconds = 60,
        private readonly bool $allowDisabledSuccess = false,
    ) {}

    public function key(): string
    {
        return 'coolify';
    }

    public function execute(ProvisioningRequest $request): ProvisioningStepResult
    {
        if (! $this->enabled && ! $this->allowDisabledSuccess) {
            return ProvisioningStepResult::failure('Coolify adapter is disabled (COOLIFY_ENABLED=false).');
        }

        try {
            $auth = $this->client->authenticate();
            if (! $auth->authenticated) {
                return ProvisioningStepResult::failure('Coolify authentication did not succeed.');
            }

            if ($this->milestone < 2) {
                return ProvisioningStepResult::success();
            }

            $servers = $this->client->listServers();
            $request->loadMissing('deployment.cluster');
            $target = trim((string) ($request->deployment?->cluster?->deployment_target ?? ''));
            if ($target === '') {
                return ProvisioningStepResult::failure('Cluster deployment_target is missing.');
            }

            $server = $servers->first(fn (CoolifyServer $s) => $s->matchesTarget($target));
            if ($server === null) {
                return ProvisioningStepResult::failure(
                    CoolifyMessageSanitizer::sanitize("No Coolify server matches deployment_target [{$target}]."),
                );
            }

            if ($this->milestone < 3) {
                return ProvisioningStepResult::success();
            }

            $this->client->listProjects();
            $applications = $this->client->listApplications();
            $command = $this->mapper->toCommand(
                $request,
                $servers,
                $applications,
                (string) ($this->applicationUuid ?? ''),
            );

            if ($this->milestone < 4) {
                return ProvisioningStepResult::success();
            }

            $existingRef = $this->execution->deploymentReference($request->id);
            if ($existingRef !== null) {
                $existingStatus = $this->client->deploymentStatus($existingRef);
                if ($existingStatus->isSuccessful()) {
                    return ProvisioningStepResult::success();
                }
                if ($existingStatus->isActive()) {
                    return $this->milestone >= 5
                        ? $this->observeUntilTerminal($existingRef)
                        : ProvisioningStepResult::success();
                }
                // Failed — allow retrigger only via retried ProvisioningRequest (this execute).
                $this->execution->forget($request->id);
            }

            $triggered = $this->client->triggerDeployment($command);
            $this->execution->rememberDeploymentReference($request->id, $triggered->deploymentReference);

            if ($this->milestone < 5) {
                return ProvisioningStepResult::success();
            }

            return $this->observeUntilTerminal($triggered->deploymentReference);
        } catch (CoolifyException $e) {
            return ProvisioningStepResult::failure($this->formatFailure($e));
        } catch (Throwable $e) {
            return ProvisioningStepResult::failure(
                CoolifyMessageSanitizer::sanitize($e->getMessage()),
            );
        }
    }

    private function observeUntilTerminal(string $deploymentReference): ProvisioningStepResult
    {
        $deadline = microtime(true) + max(1, $this->pollTimeoutSeconds);
        $interval = max(1, $this->pollIntervalSeconds);

        while (microtime(true) <= $deadline) {
            $status = $this->client->deploymentStatus($deploymentReference);

            if ($status->isSuccessful()) {
                return ProvisioningStepResult::success();
            }

            if ($status->isFailed()) {
                return ProvisioningStepResult::failure(
                    "Coolify deployment [{$status->deploymentReference}] failed with status [{$status->status}].",
                );
            }

            usleep($interval * 1_000_000);
        }

        return ProvisioningStepResult::failure(
            "Coolify deployment observation timed out after {$this->pollTimeoutSeconds}s.",
        );
    }

    private function formatFailure(CoolifyException $e): string
    {
        $parts = array_filter([
            $e->operation ? 'operation='.$e->operation : null,
            $e->httpStatus !== null ? 'http='.$e->httpStatus : null,
            $e->retryable ? 'retryable=yes' : 'retryable=no',
            CoolifyMessageSanitizer::sanitize($e->getMessage()),
        ]);

        return implode(' ', $parts);
    }
}
