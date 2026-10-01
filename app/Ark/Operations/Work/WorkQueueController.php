<?php

namespace App\Ark\Operations\Work;

use App\Ark\Operations\Communications\CommunicationsQueueChannelProjection;
use App\Ark\Operations\Communications\CommunicationsSurfaceChannel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkQueueController
{
    public function comms(
        Request $request,
        WorkSurfaceData $surfaceData,
        CommunicationsQueueChannelProjection $channelProjection,
    ): View {
        return $this($request, WorkQueue::Comms, $surfaceData, $channelProjection);
    }

    public function __invoke(
        Request $request,
        WorkQueue $queue,
        WorkSurfaceData $surfaceData,
        CommunicationsQueueChannelProjection $channelProjection,
    ): View {
        $data = $surfaceData->resolve($request, includeFullCommsQueue: $queue === WorkQueue::Comms);

        if ($queue === WorkQueue::Comms) {
            $data = $channelProjection->apply(
                $data,
                CommunicationsSurfaceChannel::fromQuery($request->query('channel')),
            );
        }

        return view('operations.work.queue', [
            ...$data,
            'work_queue' => $queue,
        ]);
    }
}
