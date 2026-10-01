<?php

namespace App\Ark\Operations\Realtime;

enum SessionEventType: string
{
    case SessionStarted = 'session_started';
    case SessionAnswered = 'session_answered';
    case SessionHeld = 'session_held';
    case SessionTransferred = 'session_transferred';
    case SessionRecordingStarted = 'session_recording_started';
    case SessionRecordingEnded = 'session_recording_ended';
    case SessionEnded = 'session_ended';

    public function label(): string
    {
        return match ($this) {
            self::SessionStarted => 'Session started',
            self::SessionAnswered => 'Session answered',
            self::SessionHeld => 'Session held',
            self::SessionTransferred => 'Session transferred',
            self::SessionRecordingStarted => 'Recording started',
            self::SessionRecordingEnded => 'Recording ended',
            self::SessionEnded => 'Session ended',
        };
    }

    public function timelineHeadline(): string
    {
        return $this->label();
    }
}
