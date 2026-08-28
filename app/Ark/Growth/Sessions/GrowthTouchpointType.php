<?php

namespace App\Ark\Growth\Sessions;

enum GrowthTouchpointType: string
{
    case PageViewed = 'page_viewed';
    case FormStarted = 'form_started';
    case FormExposed = 'form_exposed';
    case EstimateStarted = 'estimate_started';
    case EstimateSubmitted = 'estimate_submitted';
    case CallClicked = 'call_clicked';
    case TextClicked = 'text_clicked';
    case DirectionClicked = 'direction_clicked';
    case ReviewClicked = 'review_clicked';
    case SchedulerOpened = 'scheduler_opened';
    case VehicleLookup = 'vehicle_lookup';
    case CalculatorUsed = 'calculator_used';
    case CommonProblemLinkClicked = 'common_problem_link_clicked';
    case LeadSubmitted = 'lead_submitted';
    case LeadCreated = 'lead_created';
    case PhoneCall = 'phone_call';
    case AppointmentScheduled = 'appointment_scheduled';

    public function label(): string
    {
        return match ($this) {
            self::PageViewed => 'Visited page',
            self::FormStarted => 'Started form',
            self::FormExposed => 'Form exposed',
            self::EstimateStarted => 'Started estimate',
            self::EstimateSubmitted => 'Submitted estimate',
            self::CallClicked => 'Clicked call',
            self::TextClicked => 'Clicked text',
            self::DirectionClicked => 'Clicked directions',
            self::ReviewClicked => 'Clicked review',
            self::SchedulerOpened => 'Opened scheduler',
            self::VehicleLookup => 'Vehicle lookup',
            self::CalculatorUsed => 'Used calculator',
            self::CommonProblemLinkClicked => 'Common problem link',
            self::LeadSubmitted => 'Lead submitted',
            self::LeadCreated => 'Lead created',
            self::PhoneCall => 'Phone call',
            self::AppointmentScheduled => 'Appointment scheduled',
        };
    }
}
