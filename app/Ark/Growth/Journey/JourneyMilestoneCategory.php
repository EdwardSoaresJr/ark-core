<?php

namespace App\Ark\Growth\Journey;

enum JourneyMilestoneCategory: string
{
    case Acquisition = 'acquisition';
    case Engagement = 'engagement';
    case Conversation = 'conversation';
    case Voice = 'voice';
    case Appointment = 'appointment';
    case Estimate = 'estimate';
    case Decision = 'decision';
    case Production = 'production';
    case Revenue = 'revenue';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Acquisition => 'First contact',
            self::Engagement => 'Engagement',
            self::Conversation => 'Conversation',
            self::Voice => 'Call',
            self::Appointment => 'Appointment',
            self::Estimate => 'Estimate',
            self::Decision => 'Decision',
            self::Production => 'Repair',
            self::Revenue => 'Revenue',
            self::Review => 'Review',
        };
    }
}
