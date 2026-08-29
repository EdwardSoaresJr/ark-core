<?php

use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function websiteAdminSession(): array
{
    return [\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true];
}

test('legacy shop settings public surface section redirects to website manage', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'public-surface']))
        ->assertRedirect(route('website.manage'));
});

test('website manage renders public surface form', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession(websiteAdminSession())
        ->get(route('website.manage'))
        ->assertOk()
        ->assertSee('Manage your website', false)
        ->assertSee('Homepage headline', false)
        ->assertSee('Connect with us', false)
        ->assertSee('Facebook page URL', false)
        ->assertSee('Services we provide', false)
        ->assertSee('Auto Repair Demo City', false)
        ->assertSee(route('website.manage.update'), false);
});

test('website manage persists shop services list', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $current = PublicSurfaceSettings::current();

    expect(PublicSurfaceSettings::shopServicesForDisplay())->toHaveCount(6);

    $this->actingAs($admin)
        ->withSession(websiteAdminSession())
        ->patch(route('website.manage.update'), [
            'google_rating' => $current['google_rating'],
            'google_review_count' => $current['google_review_count'],
            'google_reviews_url' => $current['google_reviews_url'],
            'headline' => $current['headline'],
            'shop_services' => [
                [
                    'title' => 'Oil Changes',
                    'common_problem_slug' => '',
                    'enabled' => '1',
                ],
                [
                    'title' => 'Brake Repair Demo City',
                    'common_problem_slug' => 'brake-repair-demo-city',
                    'enabled' => '1',
                ],
                [
                    'title' => 'Hidden Service',
                    'common_problem_slug' => '',
                    // omitted enabled => disabled
                ],
            ],
        ])
        ->assertRedirect(route('website.manage'));

    $services = PublicSurfaceSettings::current()['shop_services'];

    expect($services)->toHaveCount(3)
        ->and($services[0]['title'])->toBe('Oil Changes')
        ->and($services[0]['common_problem_slug'])->toBeNull()
        ->and($services[0]['enabled'])->toBeTrue()
        ->and($services[2]['enabled'])->toBeFalse();

    expect(PublicSurfaceSettings::shopServicesForDisplay())->toHaveCount(2)
        ->and(collect(PublicSurfaceSettings::shopServicesForDisplay())->pluck('title')->all())
        ->toBe(['Oil Changes', 'Brake Repair Demo City']);

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('Oil Changes', false)
        ->assertSee('Search services', false)
        ->assertSee('\u0022title\u0022:\u0022Oil Changes\u0022', false)
        ->assertDontSee('data-public-surface-target="common-problems.Hidden Service"', false);
});

test('website performance renders health checklist and summary cards', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession(websiteAdminSession())
        ->get(route('website.performance'))
        ->assertOk()
        ->assertSee('Website health', false)
        ->assertSee('Homepage complete', false)
        ->assertSee('Leads this week', false)
        ->assertSee('Market pressure', false)
        ->assertSee('Open in Growth', false);
});

test('website performance surfaces publish queue for accepted opportunity', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $opportunity = GrowthOpportunity::factory()->create([
        'key' => 'publish-p0171-page',
        'title' => 'Publish P0171 page',
        'status' => GrowthOpportunityStatus::Accepted,
        'priority_score' => 90,
    ]);

    $this->actingAs($admin)
        ->withSession(websiteAdminSession())
        ->get(route('website.performance'))
        ->assertOk()
        ->assertSee('Next website improvement', false)
        ->assertSee('Publish P0171 page', false)
        ->assertSee(route('growth.opportunities.build', $opportunity), false);
});
