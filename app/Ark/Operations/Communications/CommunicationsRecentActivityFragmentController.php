<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationsRecentActivityFragmentController
{
    public function __invoke(
        Request $request,
        CommunicationsQueueResolver $resolver,
        CommunicationsQueueChannelProjection $channelProjection,
    ): JsonResponse {
        $active = CommunicationsSurfaceChannel::fromQuery($request->query('channel'));
        $recentActivity = $channelProjection->filterRows(
            $resolver->recentActivityFor($request->user()),
            $active,
        );

        return response()->json([
            'count' => count($recentActivity),
            'recent_activity' => $recentActivity,
            'html' => view('operations.communications.partials.recent-activity-list', [
                'rows' => $recentActivity,
            ])->render(),
        ]);
    }
}
