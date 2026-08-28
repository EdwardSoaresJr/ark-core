<?php

namespace App\Ark\Operations\Portal;

/**
 * Placeholder easter egg for Sign In — never creates a challenge or sends a code.
 */
final class PortalAccessEasterEgg
{
    public const MESSAGE = "Jenny can't sign in from here — 867-5309 is for the jukebox. Use the email or mobile number on your estimate or invoice.";

    public static function matches(string $contact): bool
    {
        return self::messageFor($contact) !== null;
    }

    public static function message(): string
    {
        return self::MESSAGE;
    }

    public static function messageFor(string $contact): ?string
    {
        $trimmed = strtolower(trim($contact));

        foreach (self::emailMessages() as $email => $message) {
            if ($trimmed === $email) {
                return $message;
            }
        }

        $digits = self::digits($trimmed);

        foreach (self::phoneMessages() as $phoneDigits => $message) {
            // Cast: PHP coerces numeric-looking array keys to int.
            $phoneDigits = (string) $phoneDigits;

            if (in_array($digits, [$phoneDigits, '1'.$phoneDigits], true)) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function emailMessages(): array
    {
        return [
            'jenny@example.com' => self::MESSAGE,
            'crashoverride@example.com' => "Crash Override can't sign in from here — hack the planet on your own time. Use the email or mobile number on your estimate or invoice.",
            'acidburn@example.com' => "Acid Burn can't sign in from here — this isn't a Gibson. Use the email or mobile number on your estimate or invoice.",
            'venkman@example.com' => "Venkman can't sign in from here — who you gonna call? Not this form. Use the email or mobile number on your estimate or invoice.",
            'ferris@example.com' => "Ferris can't sign in from here — he's taking a personal day. Use the email or mobile number on your estimate or invoice.",
            'neo@example.com' => "Neo can't sign in from here — there is no code at this address. Use the email or mobile number on your estimate or invoice.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function phoneMessages(): array
    {
        return [
            '5558675309' => self::MESSAGE,
            '7198675309' => self::MESSAGE, // prior local-area placeholder
            '5555554202' => "555-555-4202 can't sign in from here — Zero Cool already burned that line. Use the email or mobile number on your estimate or invoice.",
            '7195554202' => "555-555-4202 can't sign in from here — Zero Cool already burned that line. Use the email or mobile number on your estimate or invoice.",
            '5554202' => "555-555-4202 can't sign in from here — Zero Cool already burned that line. Use the email or mobile number on your estimate or invoice.",
            '5555552368' => "555-555-2368 can't sign in from here — that's the Ghostbusters hotline. Use the email or mobile number on your estimate or invoice.",
            '7195552368' => "555-555-2368 can't sign in from here — that's the Ghostbusters hotline. Use the email or mobile number on your estimate or invoice.",
            '5552368' => "555-555-2368 can't sign in from here — that's the Ghostbusters hotline. Use the email or mobile number on your estimate or invoice.",
            '5555552383' => "555-555-2383 can't sign in from here — Bueller? Bueller? He's not coming in today. Use the email or mobile number on your estimate or invoice.",
            '7195552383' => "555-555-2383 can't sign in from here — Bueller? Bueller? He's not coming in today. Use the email or mobile number on your estimate or invoice.",
            '5552383' => "555-555-2383 can't sign in from here — Bueller? Bueller? He's not coming in today. Use the email or mobile number on your estimate or invoice.",
            '5555550690' => "555-555-0690 can't sign in from here — the white rabbit doesn't leave a callback number. Use the email or mobile number on your estimate or invoice.",
            '7195550690' => "555-555-0690 can't sign in from here — the white rabbit doesn't leave a callback number. Use the email or mobile number on your estimate or invoice.",
            '5550690' => "555-555-0690 can't sign in from here — the white rabbit doesn't leave a callback number. Use the email or mobile number on your estimate or invoice.",
        ];
    }

    private static function digits(string $contact): string
    {
        return preg_replace('/\D+/', '', $contact) ?? '';
    }
}
