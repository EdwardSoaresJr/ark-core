<?php

use App\Ark\Operations\Leads\Public\CommonProblemAuthorityProjection;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\CommonProblemShopExperienceResolver;

test('common problem authority projection packages standard sections', function (): void {
    $problem = CommonProblemRegistry::find('subaru-overheating');

    expect($problem)->not->toBeNull();

    $authority = app(CommonProblemAuthorityProjection::class)->forProblem($problem);

    expect($authority['symptoms'])->not->toBeEmpty()
        ->and($authority['diagnostic_process'])->not->toBeEmpty()
        ->and($authority['typical_repairs'])->not->toBeEmpty()
        ->and($authority['diagnostic_process'])->not->toBe($authority['typical_repairs'])
        ->and($authority['related_problems'])->not->toBeEmpty()
        ->and($authority['often_confused_heading'])->toBe('Often sounds like')
        ->and($authority['shop_experience']['has_signals'])->toBeFalse();
});

test('transactional service pages use misconception heading instead of sounds like', function (): void {
    $problem = CommonProblemRegistry::find('auto-repair-demo-city');

    expect($problem)->not->toBeNull();

    $authority = app(CommonProblemAuthorityProjection::class)->forProblem($problem);

    expect($authority['often_confused_heading'])->toBe('Common misconceptions')
        ->and($authority['often_confused_with'][1] ?? '')->toContain('Many repairs that feel');

    $this->get(route('public.common-problems.show', 'auto-repair-demo-city'))
        ->assertOk()
        ->assertSee('Common misconceptions', false)
        ->assertSee('Many repairs that feel', false)
        ->assertDontSee('What people get wrong', false)
        ->assertDontSee('Quick-lube packages that sell', false)
        ->assertDontSee('Dealer-only myth', false)
        ->assertDontSee('Often sounds like', false);
});

test('common problem authority renders diagnostic and typical repair sections', function (): void {
    $this->get(route('public.common-problems.show', 'subaru-overheating'))
        ->assertOk()
        ->assertSee('How we check it', false)
        ->assertSee('combustion gas test', false)
        ->assertSee('Book an Appointment', false)
        ->assertDontSee('Talk to a Service Advisor', false)
        ->assertDontSee('What we see at our shop', false);
});

test('shop experience section renders when verified repair signals exist', function (): void {
    $resolver = new class extends CommonProblemShopExperienceResolver
    {
        public function forSlug(string $slug): ?array
        {
            return [
                'verified_repair_count' => 27,
                'most_common_fix' => 'External coolant leak — hose or radiator',
                'last_updated_label' => 'After 27 verified repairs',
                'average_diagnostic_time_label' => 'About 45–90 minutes',
                'repairs' => [
                    [
                        'vehicle' => '2018 Subaru Outback',
                        'summary' => 'Overheated on I-25; pressure test found split radiator hose.',
                        'outcome' => 'Coolant hose and fill — no head gasket work needed.',
                    ],
                ],
            ];
        }
    };

    $problem = CommonProblemRegistry::find('subaru-overheating');
    $authority = (new CommonProblemAuthorityProjection($resolver))->forProblem($problem);

    expect($authority['shop_experience']['has_signals'])->toBeTrue()
        ->and($authority['shop_experience']['verified_repair_count'])->toBe(27);

    $html = view('partials.public.common-problem-shop-experience', [
        'shopExperience' => $authority['shop_experience'],
    ])->render();

    expect($html)->toContain('What we see at our shop')
        ->and($html)->toContain('27 verified repairs')
        ->and($html)->toContain('Most common fix here');
});
