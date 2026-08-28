<?php

namespace App\Ark\Growth\Contracts\Operations;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LeadConvertedForGrowth
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LeadConvertedPayload $payload,
    ) {}
}
