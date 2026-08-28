<?php

namespace App\Ark\Operations\Timeline;

enum OperationalEventSource: string
{
    case ConversationMessage = 'conversation_message';
    case CallSession = 'call_session';
    case SessionEvent = 'session_event';
    case CommunicationEvent = 'communication_event';
    case OperationalEvent = 'operational_event';
    case Approval = 'approval';
    case Note = 'note';
}
