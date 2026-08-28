<?php

use App\Ark\Operations\Leads\Public\PublicBookWizardConcerns;

test('book wizard concern chips compose lead concern without new authority', function (): void {
    expect(PublicBookWizardConcerns::composeConcern('Brakes', ''))->toBe('Brakes')
        ->and(PublicBookWizardConcerns::composeConcern('Brakes', 'Grinding on stops.'))
        ->toBe("Brakes\n\nGrinding on stops.")
        ->and(PublicBookWizardConcerns::composeConcern('Something Else', 'Won’t start when cold.'))
        ->toBe('Won’t start when cold.');
});

test('book wizard matches obvious concern prefills to a chip', function (): void {
    expect(PublicBookWizardConcerns::matchCategory('Check engine light on'))->toBe('Check Engine Light')
        ->and(PublicBookWizardConcerns::matchCategory('Front brake grind'))->toBe('Brakes')
        ->and(PublicBookWizardConcerns::matchCategory('Custom odd issue'))->toBe('Something Else');
});
