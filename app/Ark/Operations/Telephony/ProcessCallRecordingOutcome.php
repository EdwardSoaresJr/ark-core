<?php

namespace App\Ark\Operations\Telephony;

enum ProcessCallRecordingOutcome
{
    case Attached;
    case Pending;
    case RecordedFailure;
    case Unmatched;
}
