<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CommunicationWorkboardController
{
    public function __invoke(
        Request $request,
        CommunicationWorkboardProjection $projection,
        CommunicationsQueueChannelProjection $channelProjection,
    ): View {
        $previousLastSeen = $request->attributes->get('operations.previous_last_seen_at');
        $previousLastSeenAt = $previousLastSeen instanceof Carbon
            ? $previousLastSeen
            : (is_string($previousLastSeen) ? Carbon::parse($previousLastSeen) : null);

        $data = $projection->resolve(
            $request->user(),
            previousLastSeenAt: $previousLastSeenAt,
            includeRecovery: true,
        );

        $data = $channelProjection->apply(
            $data,
            CommunicationsSurfaceChannel::fromQuery($request->query('channel')),
        );

        return view('operations.communications.workboard', $data);
    }
}
