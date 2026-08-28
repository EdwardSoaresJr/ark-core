<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\PublicLeadFunnelSummary;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventRecorder;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventType;

test('public lead funnel summary joins surface events with lead lifecycle', function (): void {
    $recorder = app(PublicSurfaceEventRecorder::class);
    $from = now()->subDay();
    $to = now()->addDay();

    $recorder->record('session-a', PublicSurfaceEventType::SurfaceViewed, attribution: 'google');
    $recorder->record('session-a', PublicSurfaceEventType::LeadStarted);
    $recorder->record('session-a', PublicSurfaceEventType::LeadSubmitted);
    $recorder->record('session-b', PublicSurfaceEventType::SurfaceViewed, attribution: 'direct');
    $recorder->record('session-b', PublicSurfaceEventType::LeadStarted);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Contacted,
        'concern' => 'Brakes grinding.',
        'contact_phone' => '7195550101',
        'first_contacted_at' => now(),
        'created_at' => now(),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Scheduled,
        'concern' => 'AC not cold.',
        'contact_phone' => '7195550102',
        'first_contacted_at' => now()->subHour(),
        'scheduled_at' => now(),
        'created_at' => now(),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Arrived,
        'concern' => 'Check engine light.',
        'contact_phone' => '7195550103',
        'first_contacted_at' => now()->subHours(2),
        'scheduled_at' => now()->subHour(),
        'arrived_at' => now(),
        'created_at' => now(),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Spam.',
        'contact_phone' => '7195550199',
        'created_at' => now(),
    ]);

    Lead::query()->create([
        'source' => LeadSource::Call,
        'state' => LeadState::Received,
        'concern' => 'Call lead.',
        'contact_phone' => '7195550104',
        'created_at' => now(),
    ]);

    $summary = app(PublicLeadFunnelSummary::class)->forPeriod($from, $to);

    expect($summary['visitors'])->toBe(2)
        ->and($summary['lead_started'])->toBe(2)
        ->and($summary['lead_submitted'])->toBe(1)
        ->and($summary['leads_created'])->toBe(3)
        ->and($summary['contacted'])->toBe(3)
        ->and($summary['scheduled'])->toBe(2)
        ->and($summary['arrived'])->toBe(1)
        ->and($summary['abandoned_after_start'])->toBe(1)
        ->and($summary['step_rates']['visitor_to_start'])->toBe(100.0)
        ->and($summary['step_rates']['created_to_contacted'])->toBe(100.0);
});

test('public lead funnel observation command prints funnel table', function (): void {
    $this->artisan('ark:public-lead-funnel-observation', ['--days' => 1])
        ->assertSuccessful()
        ->expectsOutputToContain('Public lead funnel observation')
        ->expectsOutputToContain('Visitors');
});
