<?php

namespace App\Ark\Operations\Timeline;

enum OperationalEventKind: string
{
    case Sms = 'sms';
    case Call = 'call';
    case MissedCall = 'missed_call';
    case Voicemail = 'voicemail';
    case InternalNote = 'internal_note';
    case Email = 'email';
    case Messenger = 'messenger';
    case Portal = 'portal';
    case Logged = 'logged';
    case EstimateViewed = 'estimate_viewed';
    case EstimateSent = 'estimate_sent';
    case Approval = 'approval';
    case Payment = 'payment';
    case PortalActivity = 'portal_activity';
    case VehicleStatus = 'vehicle_status';
    case Inspection = 'inspection';
    case Appointment = 'appointment';
    case StatusChange = 'status_change';
    case SessionActivity = 'session_activity';
    case Recording = 'recording';
    case AiSummary = 'ai_summary';
}
