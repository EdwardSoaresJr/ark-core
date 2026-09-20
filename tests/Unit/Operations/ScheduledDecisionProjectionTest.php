<?php

use App\Ark\Operations\Work\ScheduledDecisionProjection;
use Illuminate\Support\Carbon;

test('scheduled decision projection groups rows by scheduled day', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));

    $projection = app(ScheduledDecisionProjection::class)->fromRows([
        [
            'kind' => 'customer_decision_needed',
            'customer_name' => 'Ben Trainee',
            'vehicle_label' => '2018 Toyota Camry',
            'repair_order_shop_number' => 1042,
            'dollars_at_risk_label' => '$2,500',
            'detail' => 'Returns Sat Jun 14 for reminder · Waiting on payday',
            'url' => '/app/repair-orders/1',
            'schedule' => [
                'scheduled_for' => '2026-06-15',
                'scheduled_for_label' => 'Scheduled Sun Jun 15',
                'notes' => 'Waiting on payday',
                'assigned_to_label' => 'Ben Advisor',
                'created_by_user_id' => 1,
                'clear_url' => '/app/work/decision-schedules/1/clear',
            ],
        ],
        [
            'kind' => 'customer_decision_needed',
            'customer_name' => 'Jordan Lee',
            'vehicle_label' => '2020 Honda Pilot',
            'repair_order_shop_number' => 1043,
            'dollars_at_risk_label' => '$1,000',
            'detail' => 'Returns Wed Jun 10 for reminder',
            'url' => '/app/repair-orders/2',
            'schedule' => [
                'scheduled_for' => '2026-06-11',
                'scheduled_for_label' => 'Scheduled tomorrow',
                'notes' => '',
                'assigned_to_label' => 'Shop',
                'created_by_user_id' => 2,
                'clear_url' => '/app/work/decision-schedules/2/clear',
            ],
        ],
    ], null);

    expect($projection['total_count'])->toBe(2)
        ->and($projection['today'])->toBe([])
        ->and($projection['tomorrow'])->toHaveCount(1)
        ->and($projection['tomorrow'][0]['customer_name'])->toBe('Jordan Lee')
        ->and($projection['upcoming'])->toHaveCount(1)
        ->and($projection['upcoming'][0]['notes'])->toBe('Waiting on payday')
        ->and($projection['upcoming'][0]['is_mine'])->toBeFalse()
        ->and($projection['upcoming'][0]['returns_label'])->toBe('Returns Sat Jun 14 for reminder');

    Carbon::setTestNow();
});

test('scheduled decision projection normalizes appointment reminder notes', function () {
    $projection = app(ScheduledDecisionProjection::class)->fromRows([
        [
            'kind' => 'customer_decision_needed',
            'customer_name' => 'Jean-Luc Martin',
            'schedule' => [
                'scheduled_for' => now()->addDays(3)->toDateString(),
                'scheduled_for_label' => 'Scheduled Thu Jun 18',
                'notes' => 'Call and or send SMS reminder about apt tomorrow at 9am.',
                'assigned_to_label' => 'Alex Rivera',
                'created_by_user_id' => 2,
            ],
        ],
    ], null);

    expect($projection['upcoming'][0]['notes'])->toBe('Appointment reminder');
});
