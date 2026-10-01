<?php

namespace App\Ark\Dragon\Agent;

final class DragonMemoryPrivacy
{
    public static function rejectReason(string $fact): ?string
    {
        $text = trim($fact);
        if ($text === '' || mb_strlen($text) > 500) {
            return 'Memory must be a short durable standard or preference.';
        }
        if (preg_match('/\b(password|api[_-]?key|secret|ssn|social security)\b/i', $text)) {
            return 'Credentials and secrets cannot be stored as Dragon memory.';
        }
        if (preg_match('/\b(?:\d[ -]*?){13,19}\b/', $text) || preg_match('/\bcvv\b/i', $text)) {
            return 'Payment details cannot be stored as Dragon memory.';
        }
        if (preg_match('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', $text) || preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)) {
            return 'Customer contact details belong in ARK, not Dragon memory.';
        }
        if (preg_match('/\b(birthday|born on|years old|\bage\s+\d{1,2}\b)/i', $text)) {
            return 'Personal age or birthday does not belong in Dragon memory.';
        }
        if (preg_match('/\b(waiting approval|appointment at|call session|opened at|invoice #|repair order\s+\d+|ro\s*#?\s*\d+)\b/i', $text)) {
            return 'Live operational facts belong in ARK records, not Dragon memory.';
        }
        if (preg_match('/\b(saturday|sunday|weekday|business hours|closes at|opens at)\b/i', $text)
            && preg_match('/\d/', $text)) {
            return 'Hours belong in ARK Settings, not Dragon memory.';
        }

        return null;
    }
}
