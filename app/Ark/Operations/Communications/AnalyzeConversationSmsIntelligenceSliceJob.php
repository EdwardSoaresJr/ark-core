<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeConversationSmsIntelligenceSliceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public readonly int $sliceId,
    ) {}

    public function handle(ConversationSmsIntelligenceAnalyzer $analyzer): void
    {
        $slice = ConversationSmsIntelligenceSlice::query()->find($this->sliceId);

        if ($slice === null) {
            return;
        }

        $analyzer->analyze($slice);
    }
}
