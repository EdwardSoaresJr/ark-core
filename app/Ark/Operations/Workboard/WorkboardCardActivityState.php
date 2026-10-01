<?php

namespace App\Ark\Operations\Workboard;

enum WorkboardCardActivityState: string
{
    case None = 'none';
    case Ready = 'ready';
    case Sent = 'sent';
    case Engaged = 'engaged';
    case Attention = 'attention';
}
