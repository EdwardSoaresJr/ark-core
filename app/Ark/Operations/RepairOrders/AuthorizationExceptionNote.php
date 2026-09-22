<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Validation\ValidationException;

final class AuthorizationExceptionNote
{
    public const MIN_LENGTH = 20;

    /**
     * Phrases that would let an exception stand in for a customer approval.
     *
     * @var list<string>
     */
    private const CONSENT_PATTERNS = [
        '/\bcustomer\s+(said\s+yes|approved|has\s+approved|authori[sz]ed|gave\s+(the\s+)?(ok|okay)|consented)\b/i',
        '/\bverbal(ly)?\s+(ok|okay|approval|approved|authori[sz]ation|authori[sz]ed)\b/i',
        '/\b(ok|okay)\s+to\s+proceed\b/i',
        '/\bthey\s+approved\b/i',
        '/\bcustomer\s+consent\b/i',
    ];

    public static function assertSubstantive(string $note): string
    {
        $note = trim($note);

        if (mb_strlen($note) < self::MIN_LENGTH) {
            throw ValidationException::withMessages([
                'note' => 'Describe which work this exception covers and why this basis applies.',
            ]);
        }

        foreach (self::CONSENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $note) === 1) {
                throw ValidationException::withMessages([
                    'note' => 'An exception does not record customer approval. Approve the concern when the customer authorized the work.',
                ]);
            }
        }

        return $note;
    }
}
