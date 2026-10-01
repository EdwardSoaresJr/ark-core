<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Events\OperationalEvent;

final class RecommendationWorkCompletionListener
{
    public function __construct(
        private readonly RecommendationResolutionRecorder $recorder,
    ) {}

    public function handle(OperationalEvent $event): void
    {
        $this->recorder->observeOperationalEvent($event);
    }
}
