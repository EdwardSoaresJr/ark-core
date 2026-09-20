<?php

use App\Ark\Platform\ClusterAssignmentPolicy;
use App\Ark\Platform\DeploymentProfile;
use App\Ark\Platform\Provisioning\Events\ProvisioningCompleted;
use App\Ark\Platform\Provisioning\Events\ProvisioningFailed;
use App\Ark\Platform\Provisioning\Events\ProvisioningStarted;
use App\Ark\Platform\Provisioning\Events\ProvisioningStepCompleted;
use App\Ark\Platform\Provisioning\Events\ProvisioningStepFailed;
use App\Ark\Platform\Provisioning\Events\ProvisioningStepStarted;
use App\Ark\Platform\Provisioning\ProvisioningOrchestrator;
use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;
use App\Ark\Platform\ProvisioningRequest as RequestModel;
use App\Ark\Platform\ProvisioningRequestSource;
use App\Ark\Platform\ProvisioningRequestStatus;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use Database\Seeders\ClusterSeeder;
use Illuminate\Support\Facades\Event;

function makePendingProvisioningRequest(): ProvisioningRequest
{
    test()->seed(ClusterSeeder::class);

    $shop = Shop::query()->create([
        'slug' => 'orch-shop-'.uniqid(),
        'display_name' => 'Orchestrator Shop',
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

test('orchestrator runs stub steps and marks request completed', function () {
    $request = makePendingProvisioningRequest();

    $result = app(ProvisioningOrchestrator::class)->run($request);

    expect($result->status)->toBe(ProvisioningRequestStatus::Completed)
        ->and($result->completed_at)->not->toBeNull()
        ->and($result->completedStepKeys())->toBe(['coolify', 'stancl', 'dns', 'bootstrap', 'email']);
});

test('orchestrator skips completed steps on retry after failure', function () {
    $request = makePendingProvisioningRequest();

    $failOnce = new class implements ProvisioningStep
    {
        public int $calls = 0;

        public function key(): string
        {
            return 'flaky';
        }

        public function execute(RequestModel $request): ProvisioningStepResult
        {
            $this->calls++;

            if ($this->calls === 1) {
                return ProvisioningStepResult::failure('temporary');
            }

            return ProvisioningStepResult::success();
        }
    };

    $coolify = new class implements ProvisioningStep
    {
        public int $calls = 0;

        public function key(): string
        {
            return 'coolify';
        }

        public function execute(RequestModel $request): ProvisioningStepResult
        {
            $this->calls++;

            return ProvisioningStepResult::success();
        }
    };

    $orchestrator = new ProvisioningOrchestrator([$coolify, $failOnce]);

    $failed = $orchestrator->run($request);
    expect($failed->status)->toBe(ProvisioningRequestStatus::Failed)
        ->and($failed->completedStepKeys())->toBe(['coolify'])
        ->and($coolify->calls)->toBe(1);

    $completed = $orchestrator->run($failed->fresh());
    expect($completed->status)->toBe(ProvisioningRequestStatus::Completed)
        ->and($coolify->calls)->toBe(1)
        ->and($failOnce->calls)->toBe(2)
        ->and($completed->completedStepKeys())->toBe(['coolify', 'flaky']);
});

test('orchestrator emits provisioning lifecycle events', function () {
    Event::fake([
        ProvisioningStarted::class,
        ProvisioningStepStarted::class,
        ProvisioningStepCompleted::class,
        ProvisioningCompleted::class,
    ]);

    $request = makePendingProvisioningRequest();
    app(ProvisioningOrchestrator::class)->run($request);

    Event::assertDispatched(ProvisioningStarted::class);
    Event::assertDispatched(ProvisioningCompleted::class);
    Event::assertDispatched(ProvisioningStepStarted::class, 5);
    Event::assertDispatched(ProvisioningStepCompleted::class, 5);
});

test('orchestrator emits step failed and provisioning failed events', function () {
    Event::fake([
        ProvisioningStepFailed::class,
        ProvisioningFailed::class,
    ]);

    $request = makePendingProvisioningRequest();

    $failing = new class implements ProvisioningStep
    {
        public function key(): string
        {
            return 'boom';
        }

        public function execute(RequestModel $request): ProvisioningStepResult
        {
            return ProvisioningStepResult::failure('explode');
        }
    };

    (new ProvisioningOrchestrator([$failing]))->run($request);

    Event::assertDispatched(ProvisioningStepFailed::class, fn ($e) => $e->stepKey === 'boom' && $e->reason === 'explode');
    Event::assertDispatched(ProvisioningFailed::class, fn ($e) => $e->reason === 'explode');
});

test('only orchestrator marks completed — stubs never set status', function () {
    $request = makePendingProvisioningRequest();

    app(ProvisioningOrchestrator::class)->run($request);

    expect($request->fresh()->status)->toBe(ProvisioningRequestStatus::Completed);
});

test('ark:provisioning:run completes a pending request', function () {
    $request = makePendingProvisioningRequest();

    $this->artisan('ark:provisioning:run', ['id' => $request->id])
        ->assertSuccessful();

    expect($request->fresh()->status)->toBe(ProvisioningRequestStatus::Completed);
});
