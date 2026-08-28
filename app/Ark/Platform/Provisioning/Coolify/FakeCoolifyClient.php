<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use Illuminate\Support\Collection;

/**
 * Deterministic Coolify for tests and the swap test.
 */
final class FakeCoolifyClient implements CoolifyClient
{
    /** @var list<string> */
    public array $calls = [];

    public bool $failAuthenticate = false;

    public string $deploymentStatusValue = 'finished';

    public int $triggerCount = 0;

    public function authenticate(): CoolifyAuthenticationResult
    {
        $this->calls[] = 'authenticate';
        if ($this->failAuthenticate) {
            throw new CoolifyException('Coolify authenticate failed: HTTP 401', httpStatus: 401, operation: 'authenticate');
        }

        return new CoolifyAuthenticationResult(true, teamCount: 1);
    }

    public function listServers(): Collection
    {
        $this->calls[] = 'listServers';

        return collect([
            new CoolifyServer('server-uuid-1', 'coolify-server-01'),
        ]);
    }

    public function listProjects(): Collection
    {
        $this->calls[] = 'listProjects';

        return collect([
            new CoolifyProject('project-uuid-1', 'ARK'),
        ]);
    }

    public function listApplications(): Collection
    {
        $this->calls[] = 'listApplications';

        return collect([
            new CoolifyApplication('app-uuid-1', 'arksmsv2', 'server-uuid-1'),
        ]);
    }

    public function triggerDeployment(CoolifyDeploymentCommand $command): CoolifyDeploymentResult
    {
        $this->calls[] = 'triggerDeployment:'.$command->applicationUuid;
        $this->triggerCount++;

        return new CoolifyDeploymentResult('deploy-uuid-1', 'queued');
    }

    public function deploymentStatus(string $deploymentReference): CoolifyDeploymentStatus
    {
        $this->calls[] = 'deploymentStatus:'.$deploymentReference;

        return new CoolifyDeploymentStatus($deploymentReference, $this->deploymentStatusValue);
    }
}
