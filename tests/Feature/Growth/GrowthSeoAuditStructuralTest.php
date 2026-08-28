<?php

use App\Ark\Growth\Seo\Audit\SeoAuditChannel;
use App\Ark\Growth\Seo\Audit\SeoAuditEngine;
use App\Ark\Growth\Seo\Audit\SeoAuditAuthoritySource;
use App\Ark\Operations\Leads\Public\CommonProblemAuthorityWordCount;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicLeadFormCopy;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

it('runs structural audit from configuration authorities without registry crawl', function (): void {
    $audit = app(SeoAuditEngine::class)->summarize();

    $structural = $audit['channels'][SeoAuditChannel::Structural->value];

    expect($structural['findings'])->not->toBeEmpty();

    $cta = collect($structural['findings'])->firstWhere('id', 'cta_consistency');

    expect($cta)->not->toBeNull()
        ->and($cta['passed'])->toBeTrue()
        ->and($cta['authority_source'])->toBe(SeoAuditAuthoritySource::Configuration->value)
        ->and($cta['evidence'])->toContain(PublicLeadFormCopy::SUBMIT_LABEL);
});

it('counts problem authority depth from authority fields not meta description alone', function (): void {
    $sample = CommonProblemRegistry::all()[0] ?? null;

    expect($sample)->not->toBeNull();

    $count = CommonProblemAuthorityWordCount::count($sample);

    expect($count)->toBeGreaterThan(50);
});

it('exposes audit page with structural and runtime channels', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.audit'))
        ->assertOk()
        ->assertSee('Structural audit')
        ->assertSee('Runtime audit')
        ->assertSee('Configuration');
});
