<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use Illuminate\Http\Request;

class TelephonyRingLegStatusHandler
{
    private const ANSWER_STATUSES = ['answered', 'in-progress'];

    private const TERMINAL_STATUSES = ['completed', 'busy', 'no-answer', 'canceled', 'failed'];

    public function __construct(
        private readonly TelephonyRingLegCanceler $canceler,
        private readonly TelephonyRingState $ringState,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallContextBroadcaster $broadcaster,
        private readonly ProcessCallStatusAction $processCallStatus,
    ) {}

    public function handle(Request $request): bool
    {
        $parentCallSid = trim((string) $request->query('ring_parent', ''));

        if ($parentCallSid === '') {
            return false;
        }

        $endpointId = (int) $request->query('endpoint_id', 0);

        return $this->handleRingLeg($request, $parentCallSid, $endpointId);
    }

    public function handleRingLeg(Request $request, string $parentCallSid, int $endpointId): bool
    {
        if ($parentCallSid === '' || $endpointId <= 0) {
            return false;
        }

        $callStatus = strtolower(trim((string) $request->input('CallStatus', '')));
        $childCallSid = trim((string) $request->input('CallSid', ''));

        $this->rememberParallelLegCallSid($parentCallSid, $endpointId, $childCallSid, $callStatus);

        if ($callStatus === 'completed') {
            $this->terminalizeAnsweredParentCall($request, $parentCallSid);

            return true;
        }

        if (in_array($callStatus, self::TERMINAL_STATUSES, true)) {
            return true;
        }

        if (! in_array($callStatus, self::ANSWER_STATUSES, true)) {
            return true;
        }

        $endpoint = TelephonyEndpoint::query()->find($endpointId);

        if ($endpoint?->type === TelephonyEndpointType::Cell) {
            $this->ringState->markCellScreening($parentCallSid, $endpointId);
            $this->broadcaster->broadcastForParentCallSid($parentCallSid);

            return true;
        }

        $this->canceler->markAnsweredAndCancel($parentCallSid, $endpointId);

        return true;
    }

    private function terminalizeAnsweredParentCall(Request $request, string $parentCallSid): void
    {
        $state = $this->ringState->get($parentCallSid);
        $session = CallSession::query()
            ->where('provider_call_sid', $parentCallSid)
            ->first();

        $wasAnswered = ($state['answered'] ?? false)
            || $session?->status === CallSessionStatus::Answered;

        if (! $wasAnswered) {
            return;
        }

        $this->processCallStatus->execute(new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: $parentCallSid,
            fromNumber: (string) ($session?->from_number ?? ''),
            toNumber: (string) ($session?->to_number ?? ''),
            normalizedFrom: (string) ($session?->normalized_from ?? ''),
            normalizedTo: $session?->normalized_to,
            status: CallSessionStatus::Completed,
            rawPayload: $request->all(),
            direction: $session?->direction ?? CallSessionDirection::Inbound,
        ));

        $this->ringState->forget($parentCallSid);
    }

    private function rememberParallelLegCallSid(
        string $parentCallSid,
        int $endpointId,
        string $childCallSid,
        string $callStatus,
    ): void {
        if ($childCallSid === '' || ! in_array($callStatus, ['initiated', 'ringing', 'answered', 'in-progress'], true)) {
            return;
        }

        $state = $this->ringState->get($parentCallSid);

        if ($state === null || filled($state['conference_name'] ?? null)) {
            return;
        }

        $this->ringState->rememberOutboundCall($parentCallSid, $endpointId, $childCallSid);
    }
}
