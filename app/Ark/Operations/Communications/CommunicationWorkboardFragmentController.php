<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationWorkboardFragmentController
{
    public function __invoke(
        Request $request,
        CommunicationWorkboardProjection $projection,
    ): JsonResponse {
        $data = $projection->resolve($request->user());

        return response()->json([
            'counts' => $data['counts'],
            'signature' => $this->signature($data),
            'lanes' => [
                'calls' => $this->renderLane(
                    id: 'ops-comms-lane-calls',
                    label: 'Calls',
                    description: 'Live and waiting — ring, missed, and voicemail.',
                    tone: 'ready',
                    rows: $data['calls_waiting'],
                    cardPartial: 'operations.communications.partials.workboard-card-call',
                    emptyLabel: 'No calls waiting.',
                    count: (int) ($data['counts']['calls_waiting'] ?? 0),
                ),
                'new' => $this->renderLane(
                    id: 'ops-comms-lane-new',
                    label: 'New',
                    description: 'Inbound leads — website, SMS, and acquisition.',
                    tone: 'motion',
                    rows: $data['new_opportunities'],
                    cardPartial: 'operations.communications.partials.workboard-card-lead',
                    emptyLabel: 'No open leads.',
                    count: (int) ($data['counts']['new_opportunities'] ?? 0),
                ),
                'needs_shop' => $this->renderLane(
                    id: 'ops-comms-lane-needs-shop',
                    label: 'Needs shop',
                    description: 'Threads waiting on the shop — reply or resolve.',
                    tone: 'approval',
                    rows: $data['needs_shop'],
                    cardPartial: 'operations.communications.partials.workboard-card-conversation',
                    emptyLabel: 'Nothing needs the shop.',
                    count: (int) ($data['counts']['needs_shop'] ?? 0),
                ),
                'waiting_customer' => $this->renderLane(
                    id: 'ops-comms-lane-waiting-customer',
                    label: 'Waiting customer',
                    description: 'Ball is with the customer — follow up when needed.',
                    tone: 'motion',
                    rows: $data['waiting_customer'],
                    cardPartial: 'operations.communications.partials.workboard-card-waiting-customer',
                    emptyLabel: 'No threads waiting on customers.',
                    count: (int) ($data['counts']['waiting_customer'] ?? 0),
                ),
                'recently_resolved' => $this->renderLane(
                    id: 'ops-comms-lane-recently-resolved',
                    label: 'Recently resolved',
                    description: 'Closed threads in the last 7 days.',
                    tone: 'ready',
                    rows: $data['recently_resolved'],
                    cardPartial: 'operations.communications.partials.workboard-card-conversation',
                    emptyLabel: 'No recently resolved threads.',
                    count: (int) ($data['counts']['recently_resolved'] ?? 0),
                ),
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderLane(
        string $id,
        string $label,
        ?string $description,
        string $tone,
        array $rows,
        string $cardPartial,
        string $emptyLabel,
        int $count,
    ): string {
        return view('operations.communications.partials.workboard-lane', [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'tone' => $tone,
            'rows' => $rows,
            'cardPartial' => $cardPartial,
            'emptyLabel' => $emptyLabel,
            'count' => $count,
        ])->render();
    }

    /**
     * @param  array{
     *     calls_waiting: list<array<string, mixed>>,
     *     new_opportunities: list<array<string, mixed>>,
     *     needs_shop: list<array<string, mixed>>,
     *     waiting_customer: list<array<string, mixed>>,
     *     counts: array<string, int>,
     * }  $data
     */
    private function signature(array $data): string
    {
        $parts = [];

        foreach (['calls_waiting', 'new_opportunities', 'needs_shop', 'waiting_customer', 'recently_resolved'] as $lane) {
            foreach ($data[$lane] as $row) {
                $parts[] = implode(':', [
                    $row['kind'] ?? '',
                    (string) ($row['call_session_id'] ?? $row['lead_id'] ?? $row['conversation_message_id'] ?? ''),
                    (string) ($row['conversation_id'] ?? ''),
                    (string) ($row['waiting_on'] ?? $row['state'] ?? $row['state_label'] ?? ''),
                    (string) ($row['owned_by_user_id'] ?? ''),
                    (string) ($row['posture_age_label'] ?? $row['age_label'] ?? ''),
                    (string) ($row['snippet'] ?? $row['concern'] ?? ''),
                ]);
            }
        }

        $parts[] = json_encode($data['counts']);

        return md5(implode('|', $parts));
    }
}
