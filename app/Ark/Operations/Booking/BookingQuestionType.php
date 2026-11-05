<?php

namespace App\Ark\Operations\Booking;

enum BookingQuestionType: string
{
    case Text = 'text';
    case Paragraph = 'paragraph';
    case Choice = 'choice';
    case YesNo = 'yes_no';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Short answer',
            self::Paragraph => 'Paragraph',
            self::Choice => 'Pick one',
            self::YesNo => 'Yes or no',
        };
    }
}
