<?php

namespace App\Ark\Growth\Contracts\Operations;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operations dispatches when a public surface event is recorded.
 * Growth listens — no Growth imports in Operations controllers beyond dispatch.
 */
final class PublicSurfaceActivityRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PublicSurfaceActivityPayload $payload,
    ) {}
}
