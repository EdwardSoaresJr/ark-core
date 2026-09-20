<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Database\Eloquent\Model;

class EstimateCompanionPattern extends Model
{
    protected $fillable = [
        'job_key',
        'job_needles',
        'companion_key',
        'companion_label',
        'companion_needles',
        'support_count',
        'exception_count',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'job_needles' => 'array',
            'companion_needles' => 'array',
            'support_count' => 'integer',
            'exception_count' => 'integer',
        ];
    }

    public function shouldSurface(): bool
    {
        if ($this->exception_count >= $this->support_count && $this->source !== 'seed') {
            return false;
        }

        if ($this->source === 'observed' && $this->support_count < LearnEstimateCompanionPatternsAction::OBSERVED_SUPPORT_FLOOR) {
            return false;
        }

        return $this->support_count > $this->exception_count;
    }

    public function matchesJob(string $haystack): bool
    {
        if (str_contains($haystack, 'ignition timing')) {
            return false;
        }

        foreach ($this->job_needles ?? [] as $needle) {
            if (EstimateCompanionTokens::containsPhrase($haystack, (string) $needle)) {
                return true;
            }
        }

        $tokens = array_values(array_filter(explode('|', $this->job_key)));

        return EstimateCompanionTokens::containsTokens($haystack, $tokens);
    }

    public function companionPresentOn(RepairOrder $repairOrder): bool
    {
        foreach ($repairOrder->lines as $line) {
            if ($this->companionMatchesText(EstimateCompanionTokens::lineText($line))) {
                return true;
            }
        }

        return false;
    }

    public function companionMatchesText(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        if ($text === '') {
            return false;
        }

        if ($this->isLeakOnly($text)) {
            return false;
        }

        foreach ($this->companion_needles ?? [] as $needle) {
            if (EstimateCompanionTokens::containsPhrase($text, (string) $needle)) {
                return true;
            }
        }

        $tokens = array_values(array_filter(explode('|', $this->companion_key)));

        return EstimateCompanionTokens::containsTokens($text, $tokens);
    }

    private function isLeakOnly(string $text): bool
    {
        if (! preg_match('/\b(leak|stain|pressure)\b/u', $text)) {
            return false;
        }

        foreach ($this->companion_needles ?? [] as $needle) {
            $needle = mb_strtolower((string) $needle);

            if ($needle !== '' && ! str_contains($needle, 'leak') && str_contains($text, $needle)
                && preg_match('/\b(change|flush|engine oil|motor oil|antifreeze|5w|0w|10w)\b/u', $text)) {
                return false;
            }
        }

        return true;
    }
}
