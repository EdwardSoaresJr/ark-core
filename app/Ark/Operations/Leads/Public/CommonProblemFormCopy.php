<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Rotating copy variants for the Common Problems lead form — observe before picking a winner.
 */
final class CommonProblemFormCopy
{
    public const SUBMIT_LABEL = PublicLeadFormCopy::BOOK_SUBMIT_LABEL;

    private const SESSION_KEY = 'public_surface.common_problem_index_variant';

    private const COUNTER_KEY = 'public_surface.common_problem_variant_counter';

    /**
     * @return array<string, array{heading: string, subheading: string, submit_label: string}>
     */
    public static function variants(): array
    {
        return [
            'a' => [
                'heading' => 'Book an Appointment',
                'subheading' => 'If you don\'t see your symptom listed, describe what\'s going on and when you can bring the vehicle in.',
                'submit_label' => PublicLeadFormCopy::BOOK_SUBMIT_LABEL,
            ],
            'b' => [
                'heading' => 'Book an Appointment',
                'subheading' => 'Tell us what\'s happening — we\'ll confirm availability and verify the concern before any repairs.',
                'submit_label' => PublicLeadFormCopy::BOOK_SUBMIT_LABEL,
            ],
            'c' => [
                'heading' => 'Book an Appointment',
                'subheading' => 'Noise, warning light, leak, vibration — request a time and we\'ll confirm with you.',
                'submit_label' => PublicLeadFormCopy::BOOK_SUBMIT_LABEL,
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     heading: string,
     *     subheading: string,
     *     submit_label: string,
     *     surface: PublicSurfaceContext
     * }
     */
    public static function assignIndexVariant(Request $request, string $shopName): array
    {
        $key = self::resolveIndexVariantKey($request);
        $variant = self::render($key, $shopName);

        return [
            'key' => $key,
            ...$variant,
            'surface' => PublicSurfaceContext::commonProblemsIndex($key),
        ];
    }

    /**
     * @return array{heading: string, subheading: string, submit_label: string}
     */
    public static function render(string $key, string $shopName): array
    {
        $variant = self::variants()[$key] ?? self::variants()['c'];

        return [
            'heading' => $variant['heading'],
            'subheading' => str_replace('{shop}', $shopName, $variant['subheading']),
            'submit_label' => $variant['submit_label'],
        ];
    }

    private static function resolveIndexVariantKey(Request $request): string
    {
        $keys = array_keys(self::variants());

        if ($request->hasSession() && $request->session()->has(self::SESSION_KEY)) {
            $stored = (string) $request->session()->get(self::SESSION_KEY);

            if (in_array($stored, $keys, true)) {
                return $stored;
            }
        }

        Cache::add(self::COUNTER_KEY, 0);

        $counter = Cache::increment(self::COUNTER_KEY);
        if ($counter === false) {
            Cache::put(self::COUNTER_KEY, 1);
            $counter = 1;
        }

        $index = ($counter - 1) % count($keys);
        $key = $keys[$index];

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $key);
        }

        return $key;
    }
}
