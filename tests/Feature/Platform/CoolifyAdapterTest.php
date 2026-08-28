<?php

use App\Ark\Platform\ClusterAssignmentPolicy;
use App\Ark\Platform\DeploymentProfile;
use App\Ark\Platform\Provisioning\Coolify\CoolifyAdapter;
use App\Ark\Platform\Provisioning\Coolify\CoolifyApplication;
use App\Ark\Platform\Provisioning\Coolify\CoolifyAuthenticationResult;
use App\Ark\Platform\Provisioning\Coolify\CoolifyClient;
use App\Ark\Platform\Provisioning\Coolify\CoolifyDeploymentCommand;
use App\Ark\Platform\Provisioning\Coolify\CoolifyDeploymentMapper;
use App\Ark\Platform\Provisioning\Coolify\CoolifyDeploymentResult;
use App\Ark\Platform\Provisioning\Coolify\CoolifyDeploymentStatus;
use App\Ark\Platform\Provisioning\Coolify\CoolifyException;
use App\Ark\Platform\Provisioning\Coolify\CoolifyExecutionStore;
use App\Ark\Platform\Provisioning\Coolify\CoolifyServer;
use App\Ark\Platform\Provisioning\Coolify\FakeCoolifyClient;
use App\Ark\Platform\Provisioning\Coolify\HttpCoolifyClient;
use App\Ark\Platform\Provisioning\ProvisioningOrchestrator;
use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\Steps\StubBootstrapStep;
use App\Ark\Platform\Provisioning\Steps\StubDnsStep;
use App\Ark\Platform\Provisioning\Steps\StubEmailStep;
use App\Ark\Platform\Provisioning\Steps\StubStanclStep;
use App\Ark\Platform\ProvisioningRequest;
use App\Ark\Platform\ProvisioningRequestSource;
use App\Ark\Platform\ProvisioningRequestStatus;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use Database\Seeders\ClusterSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

function pendingRequestForCoolify(): ProvisioningRequest
{
    test()->seed(ClusterSeeder::class);

    $shop = Shop::query()->create([
        'slug' => 'coolify-'.uniqid(),
        'display_name' => 'Coolify Shop',
        'status' => ShopStatus::PendingProvision,
    ]);

    app(ClusterAssignmentPolicy::class)->assign($shop, DeploymentProfile::Shared);

    return ProvisioningRequest::query()->create([
        'shop_id' => $shop->id,
        'deployment_id' => $shop->fresh()->deployment->id,
        'status' => ProvisioningRequestStatus::Pending,
        'source' => ProvisioningRequestSource::Automatic,
        'requested_at' => now(),
    ]);
}

function coolifyAdapter(FakeCoolifyClient $fake, int $milestone, ?string $appUuid = 'app-uuid-1'): CoolifyAdapter
{
    return new CoolifyAdapter(
        client: $fake,
        mapper: new CoolifyDeploymentMapper,
        execution: new CoolifyExecutionStore,
        milestone: $milestone,
        applicationUuid: $appUuid,
        enabled: true,
        pollIntervalSeconds: 1,
        pollTimeoutSeconds: 3,
        allowDisabledSuccess: false,
    );
}

test('coolify adapter implements provisioning step', function () {
    expect(coolifyAdapter(new FakeCoolifyClient, 1))->toBeInstanceOf(ProvisioningStep::class)
        ->and(coolifyAdapter(new FakeCoolifyClient, 1)->key())->toBe('coolify');
});

test('milestone 1 authenticates and succeeds', function () {
    $fake = new FakeCoolifyClient;
    $result = coolifyAdapter($fake, 1)->execute(pendingRequestForCoolify());

    expect($result->succeeded)->toBeTrue()
        ->and($fake->calls)->toBe(['authenticate']);
});

test('authentication failure fails the request through the orchestrator', function () {
    $fake = new FakeCoolifyClient;
    $fake->failAuthenticate = true;

    $request = pendingRequestForCoolify();
    $orchestrator = new ProvisioningOrchestrator([
        coolifyAdapter($fake, 1),
        new StubStanclStep,
        new StubDnsStep,
        new StubBootstrapStep,
        new StubEmailStep,
    ]);

    $result = $orchestrator->run($request);

    expect($result->status)->toBe(ProvisioningRequestStatus::Failed)
        ->and($result->failure_reason)->not->toContain('Bearer ')
        ->and($result->completedStepKeys())->not->toContain('coolify');
});

test('milestone 2 discovers the assigned server', function () {
    $fake = new FakeCoolifyClient;
    $result = coolifyAdapter($fake, 2)->execute(pendingRequestForCoolify());

    expect($result->succeeded)->toBeTrue()
        ->and($fake->calls)->toBe(['authenticate', 'listServers']);
});

test('missing deployment target fails cleanly', function () {
    $fake = new FakeCoolifyClient;
    $request = pendingRequestForCoolify();
    $request->deployment->cluster->update(['deployment_target' => '']);

    $result = coolifyAdapter($fake, 2)->execute($request->fresh());

    expect($result->succeeded)->toBeFalse()
        ->and($result->failureReason)->toContain('deployment_target');
});

test('milestone 3 resolves the project and application target', function () {
    $fake = new FakeCoolifyClient;
    $result = coolifyAdapter($fake, 3)->execute(pendingRequestForCoolify());

    expect($result->succeeded)->toBeTrue()
        ->and($fake->calls)->toContain('listProjects')
        ->and($fake->calls)->toContain('listApplications');
});

test('milestone 4 triggers exactly one deployment', function () {
    $fake = new FakeCoolifyClient;
    $request = pendingRequestForCoolify();
    $adapter = coolifyAdapter($fake, 4);

    expect($adapter->execute($request)->succeeded)->toBeTrue()
        ->and($fake->triggerCount)->toBe(1)
        ->and(app(CoolifyExecutionStore::class)->deploymentReference($request->id))->toBe('deploy-uuid-1');
});

test('retry does not trigger duplicate deployment when active reference exists', function () {
    $fake = new FakeCoolifyClient;
    $fake->deploymentStatusValue = 'in_progress';
    $request = pendingRequestForCoolify();
    $adapter = coolifyAdapter($fake, 4);

    expect($adapter->execute($request)->succeeded)->toBeTrue()->and($fake->triggerCount)->toBe(1);
    expect($adapter->execute($request->fresh())->succeeded)->toBeTrue()->and($fake->triggerCount)->toBe(1);
});

test('milestone 5 succeeds when coolify reports completion', function () {
    $fake = new FakeCoolifyClient;
    $fake->deploymentStatusValue = 'finished';

    expect(coolifyAdapter($fake, 5)->execute(pendingRequestForCoolify())->succeeded)->toBeTrue()
        ->and($fake->calls)->toContain('deploymentStatus:deploy-uuid-1');
});

test('milestone 5 fails on terminal deployment failure', function () {
    $fake = new FakeCoolifyClient;
    $fake->deploymentStatusValue = 'failed';

    $result = coolifyAdapter($fake, 5)->execute(pendingRequestForCoolify());

    expect($result->succeeded)->toBeFalse()
        ->and($result->failureReason)->toContain('failed');
});

test('milestone 5 fails on timeout', function () {
    $fake = new FakeCoolifyClient;
    $fake->deploymentStatusValue = 'queued';

    $adapter = new CoolifyAdapter(
        client: $fake,
        milestone: 5,
        applicationUuid: 'app-uuid-1',
        enabled: true,
        pollIntervalSeconds: 1,
        pollTimeoutSeconds: 2,
    );

    $result = $adapter->execute(pendingRequestForCoolify());

    expect($result->succeeded)->toBeFalse()
        ->and($result->failureReason)->toContain('timed out');
});

test('secrets are absent from failure messages', function () {
    config(['ark-platform.coolify.token' => 'super-secret-token-value']);

    Http::fake([
        'https://coolify.test/api/v1/teams' => Http::response(['error' => 'Bearer super-secret-token-value denied'], 401),
    ]);

    $client = new HttpCoolifyClient('https://coolify.test', 'super-secret-token-value');

    try {
        $client->authenticate();
        expect(false)->toBeTrue();
    } catch (CoolifyException $e) {
        expect($e->getMessage())->not->toContain('super-secret-token-value')
            ->and($e->getMessage())->toContain('[redacted]');
    }
});

test('fake coolify client can complete full orchestration', function () {
    $request = pendingRequestForCoolify();
    $fake = new FakeCoolifyClient;

    $result = (new ProvisioningOrchestrator([
        coolifyAdapter($fake, 1),
        new StubStanclStep,
        new StubDnsStep,
        new StubBootstrapStep,
        new StubEmailStep,
    ]))->run($request);

    expect($result->status)->toBe(ProvisioningRequestStatus::Completed);
});

test('swap test remains green with alternate coolify client', function () {
    $forgeShaped = new class implements CoolifyClient
    {
        public function authenticate(): CoolifyAuthenticationResult
        {
            return new CoolifyAuthenticationResult(true, 1);
        }

        public function listServers(): Collection
        {
            return collect([
                new CoolifyServer('server-uuid-1', 'coolify-server-01'),
            ]);
        }

        public function listProjects(): Collection
        {
            return collect();
        }

        public function listApplications(): Collection
        {
            return collect([
                new CoolifyApplication('app-uuid-1', 'arksmsv2'),
            ]);
        }

        public function triggerDeployment(CoolifyDeploymentCommand $command): CoolifyDeploymentResult
        {
            return new CoolifyDeploymentResult('forge-1');
        }

        public function deploymentStatus(string $deploymentReference): CoolifyDeploymentStatus
        {
            return new CoolifyDeploymentStatus($deploymentReference, 'finished');
        }
    };

    $request = pendingRequestForCoolify();
    $adapter = new CoolifyAdapter(client: $forgeShaped, milestone: 1, enabled: true);

    $result = (new ProvisioningOrchestrator([
        $adapter,
        new StubStanclStep,
        new StubDnsStep,
        new StubBootstrapStep,
        new StubEmailStep,
    ]))->run($request);

    expect($result->status)->toBe(ProvisioningRequestStatus::Completed);
});

test('http coolify client deploy uses get deploy endpoint', function () {
    Http::fake([
        'https://coolify.test/api/v1/deploy*' => Http::response([
            'deployments' => [['deployment_uuid' => 'd-1', 'status' => 'queued']],
        ], 200),
    ]);

    $client = new HttpCoolifyClient('https://coolify.test', 'token');
    $result = $client->triggerDeployment(new CoolifyDeploymentCommand('app-1', 'server-1', 'coolify-server-01'));

    expect($result->deploymentReference)->toBe('d-1');
    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/api/v1/deploy'));
});

test('default bound coolify adapter completes orchestration in testing', function () {
    $request = pendingRequestForCoolify();

    $result = app(ProvisioningOrchestrator::class)->run($request);

    expect($result->status)->toBe(ProvisioningRequestStatus::Completed)
        ->and($result->completedStepKeys())->toContain('coolify');
});
