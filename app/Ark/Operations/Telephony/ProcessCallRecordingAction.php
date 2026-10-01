<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\Request;

class ProcessCallRecordingAction
{
    public function __construct(
        private readonly AttachCallRecordingAction $attach,
    ) {}

    public function execute(Request $request, bool $voicemail = false): ProcessCallRecordingOutcome
    {
        $callSid = trim((string) $request->input('CallSid', ''));
        $parentCallSid = trim((string) $request->input('ParentCallSid', ''));
        $dialCallSid = trim((string) $request->input('DialCallSid', ''));
        $recordingSid = trim((string) $request->input('RecordingSid', ''));
        $recordingUrl = trim((string) $request->input('RecordingUrl', ''));
        $duration = (int) $request->input('RecordingDuration', 0);
        $recordingStatus = strtolower(trim((string) $request->input('RecordingStatus', '')));

        $session = $this->attach->findSession(
            $callSid !== '' ? $callSid : null,
            $parentCallSid !== '' ? $parentCallSid : null,
            $dialCallSid !== '' ? $dialCallSid : null,
        );

        if ($recordingUrl === '') {
            if (in_array($recordingStatus, ['in-progress', ''], true)) {
                return ProcessCallRecordingOutcome::Pending;
            }

            if ($session === null) {
                return ProcessCallRecordingOutcome::Unmatched;
            }

            $this->attach->markFailed($session, 'Recording callback had no URL (status '.$recordingStatus.').', $voicemail);

            return ProcessCallRecordingOutcome::RecordedFailure;
        }

        if ($session === null) {
            return ProcessCallRecordingOutcome::Unmatched;
        }

        $this->attach->attach(
            $session,
            $recordingUrl,
            $duration,
            $recordingSid !== '' ? $recordingSid : null,
            $voicemail,
        );

        return ProcessCallRecordingOutcome::Attached;
    }
}
