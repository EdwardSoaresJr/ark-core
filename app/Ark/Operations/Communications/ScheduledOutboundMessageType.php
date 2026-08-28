<?php

namespace App\Ark\Operations\Communications;

enum ScheduledOutboundMessageType: string
{
    case EstimateSend = 'estimate_send';

    case SmsReply = 'sms_reply';
}
