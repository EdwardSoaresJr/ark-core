<?php

use App\Ark\Platform\Cluster;
use App\Ark\Platform\ClusterAssignmentPolicy;
use App\Ark\Platform\ClusterAssignmentSource;
use App\Ark\Platform\ClusterStatus;
use App\Ark\Platform\ClusterType;
use App\Ark\Platform\DeploymentProfile;
use App\Ark\Platform\ProvisioningRequest;
use App\Ark\Platform\ProvisioningRequestSource;
use App\Ark\Platform\ProvisioningRequestStatus;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\ClusterSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('cluster seeder creates assignable shared cluster a', function () {
    $this->seed(ClusterSeeder::class);

    $cluster = Cluster::query()->where('slug', 'shared-a')->first();

    expect($cluster)->not->toBeNull()
        ->and($cluster->name)->toBe('Shared Cluster A')
        ->and($cluster->type)->toBe(ClusterType::Shared)
        ->and($cluster->status)->toBe(ClusterStatus::Healthy)
        ->and($cluster->accepting_new_shops)->toBeTrue()
        ->and($cluster->deployment_target)->toBe('coolify-server-01')
        ->and($cluster->ingress_endpoint)->toBe('https://shared-a.internal');
});

test('cluster assignment policy places shop and records history', function () {
    $this->seed(ClusterSeeder::class);

    $shop = Shop::query()->create([
        'slug' => 'testgarage',
        'display_name' => 'Test Garage',
        'legal_name' => 'Test Garage LLC',
        'status' => ShopStatus::PendingProvision,
    ]);

    $assignment = app(ClusterAssignmentPolicy::class)->assign(
        $shop,
        DeploymentProfile::Shared,
        ClusterAssignmentSource::Automatic,
        reason: 'Provisioning v1 placement',
    );

    $shop->refresh();

    expect($assignment->cluster->slug)->toBe('shared-a')
        ->and($assignment->source)->toBe(ClusterAssignmentSource::Automatic)
        ->and($assignment->reason)->toBe('Provisioning v1 placement')
        ->and($assignment->isCurrent())->toBeTrue()
        ->and($shop->deployment?->cluster_id)->toBe($assignment->cluster_id)
        ->and($shop->deployment?->profile)->toBe(DeploymentProfile::Shared)
        ->and($assignment->cluster->fresh()->currentShopCount())->toBe(1);
});

test('reassignment supersedes prior assignment and keeps previous cluster', function () {
    $sharedA = Cluster::query()->create([
        'name' => 'Shared Cluster A',
        'slug' => 'shared-a',
        'type' => ClusterType::Shared,
        'status' => ClusterStatus::Healthy,
        'accepting_new_shops' => true,
        'deployment_target' => 'coolify-server-01',
        'ingress_endpoint' => 'https://shared-a.internal',
    ]);

    $sharedB = Cluster::query()->create([
        'name' => 'Shared Cluster B',
        'slug' => 'shared-b',
        'type' => ClusterType::Shared,
        'status' => ClusterStatus::Healthy,
        'accepting_new_shops' => true,
        'deployment_target' => 'coolify-server-02',
        'ingress_endpoint' => 'https://shared-b.internal',
    ]);

    $shop = Shop::query()->create([
        'slug' => 'joesauto',
        'display_name' => "Joe's Auto",
        'status' => ShopStatus::Active,
    ]);

    $policy = app(ClusterAssignmentPolicy::class);
    $first = $policy->assign($shop, DeploymentProfile::Shared, reason: 'Initial');

    // Prefer B next by closing A to new shops (utilization would also work).
    $sharedA->update(['accepting_new_shops' => false]);

    $second = $policy->assign(
        $shop->fresh(),
        DeploymentProfile::Shared,
        ClusterAssignmentSource::Manual,
        reason: 'Rebalance',
    );

    expect($first->fresh()->superseded_at)->not->toBeNull()
        ->and($second->cluster_id)->toBe($sharedB->id)
        ->and($second->previous_cluster_id)->toBe($sharedA->id)
        ->and($second->source)->toBe(ClusterAssignmentSource::Manual)
        ->and($shop->fresh()->deployment?->cluster_id)->toBe($sharedB->id)
        ->and($shop->clusterAssignments()->count())->toBe(2);
});

test('policy skips clusters that are not accepting new shops', function () {
    Cluster::query()->create([
        'name' => 'Shared Cluster A',
        'slug' => 'shared-a',
        'type' => ClusterType::Shared,
        'status' => ClusterStatus::Healthy,
        'accepting_new_shops' => false,
        'deployment_target' => 'coolify-server-01',
        'ingress_endpoint' => 'https://shared-a.internal',
    ]);

    $shop = Shop::query()->create([
        'slug' => 'fullhouse',
        'display_name' => 'Full House',
        'status' => ShopStatus::PendingProvision,
    ]);

    expect(fn () => app(ClusterAssignmentPolicy::class)->assign($shop, DeploymentProfile::Shared))
        ->toThrow(RuntimeException::class);
});

test('master admin can view hidden clusters index', function () {
    $this->seed(ClusterSeeder::class);

    $admin = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('platform.clusters.index'))
        ->assertOk()
        ->assertSee('Shared Cluster A')
        ->assertSee('Yes')
        ->assertSee('coolify-server-01');
});

test('non master admin cannot view clusters index', function () {
    $admin = User::factory()->create(['is_master_admin' => false])->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('platform.clusters.index'))
        ->assertForbidden();
});

test('provisioning v1 assignment does not create a provisioning request', function () {
    $this->seed(ClusterSeeder::class);

    $shop = Shop::query()->create([
        'slug' => 'truth-only',
        'display_name' => 'Truth Only',
        'status' => ShopStatus::PendingProvision,
    ]);

    app(ClusterAssignmentPolicy::class)->assign($shop, DeploymentProfile::Shared);

    expect(ProvisioningRequest::query()->count())->toBe(0)
        ->and($shop->fresh()->deployment)->not->toBeNull()
        ->and($shop->clusterAssignments()->count())->toBe(1);
});

test('provisioning request can be recorded as pending workflow authority', function () {
    $this->seed(ClusterSeeder::class);

    $shop = Shop::query()->create([
        'slug' => 'request-shop',
        'display_name' => 'Request Shop',
        'status' => ShopStatus::PendingProvision,
    ]);

    app(ClusterAssignmentPolicy::class)->assign($shop, DeploymentProfile::Shared);
    $deployment = $shop->fresh()->deployment;

    $request = ProvisioningRequest::query()->create([
        'shop_id' => $shop->id,
        'deployment_id' => $deployment->id,
        'status' => ProvisioningRequestStatus::Pending,
        'source' => ProvisioningRequestSource::Automatic,
        'requested_at' => now(),
    ]);

    expect($request->status)->toBe(ProvisioningRequestStatus::Pending)
        ->and($shop->provisioningRequests()->count())->toBe(1)
        ->and($deployment->provisioningRequests()->first()?->is($request))->toBeTrue();
});
