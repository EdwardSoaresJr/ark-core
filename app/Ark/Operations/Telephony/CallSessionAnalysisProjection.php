<?php

namespace App\Ark\Operations\Telephony;

final class CallSessionAnalysisProjection
{
    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public static function fromDecoded(array $decoded): array
    {
        return [
            'summary' => trim((string) ($decoded['summary'] ?? '')),
            'customer_intent' => trim((string) ($decoded['customer_intent'] ?? '')),
            'outcome' => trim((string) ($decoded['outcome'] ?? '')),
            'sentiment' => self::sentiment((string) ($decoded['sentiment'] ?? 'neutral')),
            'follow_up_needed' => (bool) ($decoded['follow_up_needed'] ?? false),
            'follow_up_notes' => self::nullableString($decoded['follow_up_notes'] ?? null),
            'suggested_reply' => self::nullableString($decoded['suggested_reply'] ?? null),
            'missed_upsell' => (bool) ($decoded['missed_upsell'] ?? false),
            'missed_upsell_notes' => self::nullableString($decoded['missed_upsell_notes'] ?? null),
            'empathy_score' => self::score($decoded['empathy_score'] ?? null),
            'empathy_notes' => self::nullableString($decoded['empathy_notes'] ?? null),
            'ownership_score' => self::score($decoded['ownership_score'] ?? null),
            'clarity_score' => self::score($decoded['clarity_score'] ?? null),
            'appointment_captured' => self::nullableBool($decoded['appointment_captured'] ?? null),
            'appointment_notes' => self::nullableString($decoded['appointment_notes'] ?? null),
            'coaching_priority' => self::coachingPriority((string) ($decoded['coaching_priority'] ?? 'none')),
            'coaching_notes' => self::nullableString($decoded['coaching_notes'] ?? null),
            'coaching_strengths' => self::stringList($decoded['coaching_strengths'] ?? [], 3),
            'coaching_improvements' => self::stringList($decoded['coaching_improvements'] ?? [], 3),
            'topics' => self::stringList($decoded['topics'] ?? [], 5),
        ];
    }

    public static function empathyLabel(?int $score): ?string
    {
        return match ($score) {
            5 => 'Excellent',
            4 => 'Strong',
            3 => 'Adequate',
            2 => 'Weak',
            1 => 'Poor',
            default => null,
        };
    }

    public static function scoreLabel(?int $score): ?string
    {
        return self::empathyLabel($score);
    }

    public static function sentimentLabel(string $sentiment): string
    {
        return match (self::sentiment($sentiment)) {
            'positive' => 'Positive mood',
            'neutral' => 'Neutral mood',
            'concerned' => 'Concerned',
            'frustrated' => 'Frustrated / angry',
            default => 'Unknown mood',
        };
    }

    /**
     * @return list<string, string>
     */
    public static function sentimentToneClasses(string $sentiment): array
    {
        return match (self::sentiment($sentiment)) {
            'positive' => ['bg-emerald-100', 'text-emerald-900', 'border-emerald-200'],
            'neutral' => ['bg-slate-100', 'text-slate-700', 'border-slate-200'],
            'concerned' => ['bg-amber-100', 'text-amber-950', 'border-amber-200'],
            'frustrated' => ['bg-rose-100', 'text-rose-950', 'border-rose-200'],
            default => ['bg-slate-100', 'text-slate-700', 'border-slate-200'],
        };
    }

    public static function advisorScoreToneClasses(?int $score): array
    {
        if ($score === null) {
            return ['bg-slate-100', 'text-slate-700', 'border-slate-200'];
        }

        if ($score >= 4) {
            return ['bg-emerald-100', 'text-emerald-900', 'border-emerald-200'];
        }

        if ($score === 3) {
            return ['bg-slate-100', 'text-slate-800', 'border-slate-200'];
        }

        return ['bg-rose-100', 'text-rose-950', 'border-rose-200'];
    }

    public static function coachingPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'high' => 'High coaching',
            'medium' => 'Coaching',
            'low' => 'Light coaching',
            default => '',
        };
    }

    /**
     * Higher weight = more urgent coaching need.
     *
     * @param  array<string, mixed>  $analysis
     */
    public static function coachingUrgencyWeight(array $analysis): int
    {
        $priority = self::coachingPriority((string) data_get($analysis, 'coaching_priority', 'none'));

        $weight = match ($priority) {
            'high' => 300,
            'medium' => 200,
            'low' => 100,
            default => 0,
        };

        if ($weight === 0) {
            return 0;
        }

        $empathy = self::score(data_get($analysis, 'empathy_score'));
        $weight += (6 - ($empathy ?? 3)) * 10;

        if ((bool) data_get($analysis, 'missed_upsell', false)) {
            $weight += 8;
        }

        if (data_get($analysis, 'appointment_captured') === false) {
            $weight += 5;
        }

        $ownership = self::score(data_get($analysis, 'ownership_score'));
        if ($ownership !== null && $ownership <= 2) {
            $weight += 4;
        }

        $clarity = self::score(data_get($analysis, 'clarity_score'));
        if ($clarity !== null && $clarity <= 2) {
            $weight += 4;
        }

        return $weight;
    }

    public static function coachingPriorityRank(string $priority): int
    {
        return match (self::coachingPriority($priority)) {
            'high' => 1,
            'medium' => 2,
            'low' => 3,
            default => 99,
        };
    }

    private static function sentiment(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['positive', 'neutral', 'concerned', 'frustrated'], true)
            ? $normalized
            : 'neutral';
    }

    private static function coachingPriority(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['none', 'low', 'medium', 'high'], true)
            ? $normalized
            : 'none';
    }

    public static function scoreFromRaw(mixed $value): ?int
    {
        return self::score($value);
    }

    private static function score(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $score = (int) round((float) $value);

        return max(1, min(5, $score));
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (bool) $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values, int $limit): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->take($limit)
            ->values()
            ->all();
    }
}
