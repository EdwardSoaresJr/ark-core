<?php

namespace App\Ark\Operations\Messaging;

enum SmsConsentKeyword: string
{
    case Stop = 'stop';
    case StopAll = 'stopall';
    case Unsubscribe = 'unsubscribe';
    case Cancel = 'cancel';
    case End = 'end';
    case Quit = 'quit';
    case Start = 'start';
    case Unstop = 'unstop';
    case Yes = 'yes';

    public function isOptOut(): bool
    {
        return match ($this) {
            self::Stop,
            self::StopAll,
            self::Unsubscribe,
            self::Cancel,
            self::End,
            self::Quit => true,
            default => false,
        };
    }

    public function isOptIn(): bool
    {
        return match ($this) {
            self::Start,
            self::Unstop,
            self::Yes => true,
            default => false,
        };
    }

    public static function matchOptOut(string $body): ?self
    {
        $normalized = self::normalizeBody($body);

        foreach (self::cases() as $keyword) {
            if ($keyword->isOptOut() && $keyword->value === $normalized) {
                return $keyword;
            }
        }

        return null;
    }

    public static function matchOptIn(string $body): ?self
    {
        $normalized = self::normalizeBody($body);

        foreach (self::cases() as $keyword) {
            if ($keyword->isOptIn() && $keyword->value === $normalized) {
                return $keyword;
            }
        }

        return null;
    }

    private static function normalizeBody(string $body): string
    {
        return strtolower(trim($body));
    }
}
