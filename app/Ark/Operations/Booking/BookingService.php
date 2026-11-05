<?php

namespace App\Ark\Operations\Booking;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An appointment type a customer can choose online (Brakes, Oil change, Mobile diagnostic…).
 *
 * options: one level of choices under the service (Front / Rear / Both), each with an optional
 * duration and an optional "details required" rule, as the original Book Service Builder had.
 *
 * @property list<array{label: string, duration_minutes: int|null, requires_details: bool}>|null $options
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'duration_minutes',
    'labor_hours',
    'concern_prompt',
    'location_mode',
    'requires_approval',
    'is_public',
    'options',
    'position',
    'archived_at',
])]
class BookingService extends Model
{
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'labor_hours' => 'decimal:2',
            'location_mode' => BookingLocationMode::class,
            'requires_approval' => 'boolean',
            'is_public' => 'boolean',
            'options' => 'array',
            'position' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BookingService $service): void {
            if (! filled($service->slug)) {
                $service->slug = self::uniqueSlug((string) $service->name, $service->id);
            }
        });
    }

    /** @param Builder<BookingService> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @param Builder<BookingService> $query */
    public function scopeOfferedPublicly(Builder $query): void
    {
        $query->whereNull('archived_at')->where('is_public', true);
    }

    /** @param Builder<BookingService> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * @return list<array{label: string, duration_minutes: int|null, requires_details: bool}>
     */
    public function optionList(): array
    {
        $out = [];
        foreach (is_array($this->options) ? $this->options : [] as $option) {
            $label = trim((string) ($option['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $minutes = isset($option['duration_minutes']) && is_numeric($option['duration_minutes'])
                ? max(15, min(480, (int) $option['duration_minutes']))
                : null;
            $out[] = [
                'label' => mb_substr($label, 0, 120),
                'duration_minutes' => $minutes,
                'requires_details' => filter_var($option['requires_details'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return $out;
    }

    /**
     * @return array{label: string, duration_minutes: int|null, requires_details: bool}|null
     */
    public function option(?string $label): ?array
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        foreach ($this->optionList() as $option) {
            if (strcasecmp($option['label'], trim($label)) === 0) {
                return $option;
            }
        }

        return null;
    }

    public function durationFor(?string $optionLabel): int
    {
        return $this->option($optionLabel)['duration_minutes'] ?? max(15, (int) $this->duration_minutes);
    }

    /**
     * Expected workload one vehicle adds to the day. Labor hours when the shop set them,
     * otherwise the visit length, matching SchedulingCapacityCalculator's fallback.
     */
    public function workloadHoursFor(?string $optionLabel): float
    {
        if ($this->labor_hours !== null && (float) $this->labor_hours > 0) {
            return (float) $this->labor_hours;
        }

        return round($this->durationFor($optionLabel) / 60, 2);
    }

    public function locationMode(): BookingLocationMode
    {
        return $this->location_mode instanceof BookingLocationMode ? $this->location_mode : BookingLocationMode::Shop;
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $base = mb_substr($base, 0, 70);
        $slug = $base;
        $n = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
