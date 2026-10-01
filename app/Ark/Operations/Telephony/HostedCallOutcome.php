<?php

namespace App\Ark\Operations\Telephony;

/**
 * Normalized hosted-voice facts from Platform. String values are the fabric contract.
 */
enum HostedCallOutcome: string
{
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Missed = 'missed';
    case VoicemailOffered = 'voicemail_offered';
    case VoicemailLeft = 'voicemail_left';
    case Completed = 'completed';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function sessionStatus(): CallSessionStatus
    {
        return match ($this) {
            self::Ringing => CallSessionStatus::Ringing,
            self::Answered => CallSessionStatus::Answered,
            self::Missed, self::VoicemailOffered, self::VoicemailLeft => CallSessionStatus::Missed,
            self::Completed => CallSessionStatus::Completed,
            self::Failed => CallSessionStatus::Failed,
            self::Unknown => CallSessionStatus::Unknown,
        };
    }

    public function wasAnswered(): bool
    {
        return $this === self::Answered || $this === self::Completed;
    }
}
