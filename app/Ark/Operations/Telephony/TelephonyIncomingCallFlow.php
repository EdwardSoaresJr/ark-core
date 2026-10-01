<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;

class TelephonyIncomingCallFlow
{
    public function __construct(
        private readonly TelephonyRingGroup $ringGroup,
        private readonly TelephonyCallFlowSettings $flow,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(
            app(TelephonyRingGroup::class),
            TelephonyCallFlowSettings::fromShopSettings(),
        );
    }

    public function buildResponse(string $parentCallSid, ?string $callerNumber = null): string
    {
        $callerNumber = $this->resolveCallerNumber($parentCallSid, $callerNumber);

        if (! $this->flow->isOpenForCaller($callerNumber)) {
            return $this->buildVoicemailResponse($this->flow->closedGreeting());
        }

        if (! $this->ringGroup->hasIncomingRingTargets(callerNumber: $callerNumber)) {
            return $this->buildVoicemailResponse($this->flow->voicemailGreeting());
        }

        if ($this->ringGroup->usesStaggeredRing(callerNumber: $callerNumber)) {
            return $this->buildStaggeredTieredResponse($parentCallSid, $callerNumber);
        }

        $dialChildren = $this->ringGroup->buildDialChildrenXml(parentCallSid: $parentCallSid, callerNumber: $callerNumber);

        if ($dialChildren === '') {
            return $this->buildVoicemailResponse($this->flow->voicemailGreeting());
        }

        return $this->buildOpenHoursDialResponse($dialChildren, $parentCallSid);
    }

    public function buildStaggeredExpandResponse(string $parentCallSid, int $maxDelaySeconds, ?string $callerNumber = null): string
    {
        $callerNumber = $this->resolveCallerNumber($parentCallSid, $callerNumber);
        $dialChildren = $this->ringGroup->buildDialChildrenForMaxDelay(
            $maxDelaySeconds,
            parentCallSid: $parentCallSid,
            callerNumber: $callerNumber,
        );

        if ($dialChildren === '') {
            return $this->buildVoicemailResponse($this->flow->voicemailGreeting());
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'
            .'<Dial'.$this->dialAttributes($parentCallSid).'>'
            .$dialChildren
            .'</Dial>'
            .'</Response>';
    }

    public function shouldDispatchStaggeredRing(?string $callerNumber = null): bool
    {
        return $this->flow->isOpenForCaller($callerNumber)
            && $this->ringGroup->usesStaggeredRing(callerNumber: $callerNumber)
            && $this->ringGroup->hasIncomingRingTargets(callerNumber: $callerNumber);
    }

    public function buildVoicemailResponse(string $greeting): string
    {
        $recordingCallback = '';
        $voicemailAction = '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'
            .'<Say voice="alice">'.htmlspecialchars($greeting, ENT_XML1).'</Say>'
            .'<Record maxLength="180" playBeep="true" timeout="5"'
            .' action="'.htmlspecialchars($voicemailAction, ENT_XML1).'"'
            .' recordingStatusCallback="'.htmlspecialchars($recordingCallback, ENT_XML1).'"'
            .' recordingStatusCallbackMethod="POST"'
            .' />'
            .'</Response>';
    }

    private function buildStaggeredTieredResponse(string $parentCallSid, ?string $callerNumber = null): string
    {
        $conferenceName = TelephonyConferenceName::forParentCall($parentCallSid);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'
            .$this->disclaimerSay()
            .'<Dial'.$this->staggeredConferenceDialAttributes($parentCallSid).'>'
            .''
            .'</Dial>'
            .'</Response>';
    }

    private function buildOpenHoursDialResponse(string $dialChildren, ?string $parentCallSid = null): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response>'
            .$this->disclaimerSay()
            .'<Dial'.$this->dialAttributes($parentCallSid).'>'
            .$dialChildren
            .'</Dial>'
            .'</Response>';
    }

    private function disclaimerSay(): string
    {
        if (! $this->flow->recordInboundCalls()) {
            return '';
        }

        return '<Say voice="alice">'.htmlspecialchars($this->flow->recordingDisclaimer(), ENT_XML1).'</Say>';
    }

    private function dialAttributes(?string $parentCallSid = null): string
    {
        $statusCallback = '';
        $dialComplete = '';
        $recordingCallback = '';
        $timeout = $this->flow->dialTimeoutSeconds();
        $callerId = $parentCallSid !== null ? $this->customerCallerIdE164($parentCallSid) : null;
        $callerAttr = filled($callerId)
            ? ' callerId="'.htmlspecialchars($callerId, ENT_XML1).'"'
            : '';

        $attributes = $callerAttr
            .TelephonyCallerRingtone::forCurrentShop()->dialAttribute()
            .' statusCallback="'.htmlspecialchars($statusCallback, ENT_XML1).'"'
            .' statusCallbackEvent="initiated ringing answered completed"'
            .' statusCallbackMethod="POST"'
            .' timeout="'.$timeout.'"'
            .' action="'.htmlspecialchars($dialComplete, ENT_XML1).'"';

        if ($this->flow->recordInboundCalls()) {
            $attributes .= ' record="record-from-answer"'
                .' recordingStatusCallback="'.htmlspecialchars($recordingCallback, ENT_XML1).'"'
                .' recordingStatusCallbackMethod="POST"';
        }

        return $attributes;
    }

    private function staggeredConferenceDialAttributes(?string $parentCallSid = null): string
    {
        $statusCallback = '';
        $timeout = $this->flow->dialTimeoutSeconds();
        $callerId = $parentCallSid !== null ? $this->customerCallerIdE164($parentCallSid) : null;
        $callerAttr = filled($callerId)
            ? ' callerId="'.htmlspecialchars($callerId, ENT_XML1).'"'
            : '';

        return $callerAttr
            .TelephonyCallerRingtone::forCurrentShop()->dialAttribute()
            .' statusCallback="'.htmlspecialchars($statusCallback, ENT_XML1).'"'
            .' statusCallbackEvent="initiated ringing answered completed"'
            .' statusCallbackMethod="POST"'
            .' timeout="'.$timeout.'"';
    }

    private function customerCallerIdE164(string $parentCallSid): ?string
    {
        $state = app(TelephonyRingState::class)->get($parentCallSid);
        $fromState = PhoneNumber::toE164($state['customer_caller_id'] ?? null);

        if ($fromState !== null) {
            return $fromState;
        }

        $session = CallSession::query()
            ->where('provider_call_sid', $parentCallSid)
            ->first(['from_number', 'normalized_from']);

        return PhoneNumber::toE164($session?->normalized_from)
            ?? PhoneNumber::toE164($session?->from_number);
    }

    private function resolveCallerNumber(string $parentCallSid, ?string $callerNumber): ?string
    {
        $normalized = PhoneNumber::normalize($callerNumber);

        if ($normalized !== null) {
            return $normalized;
        }

        return PhoneNumber::normalize($this->customerCallerIdE164($parentCallSid));
    }
}
