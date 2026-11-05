<?php

namespace App\Ark\Operations\Booking;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A shop-defined question on the booking form, for every service or only some.
 *
 * @property list<string>|null $choices
 * @property list<int>|null $service_ids
 */
#[Fillable([
    'label',
    'help',
    'type',
    'choices',
    'required',
    'service_ids',
    'position',
    'archived_at',
])]
class BookingQuestion extends Model
{
    protected function casts(): array
    {
        return [
            'type' => BookingQuestionType::class,
            'choices' => 'array',
            'required' => 'boolean',
            'service_ids' => 'array',
            'position' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @param Builder<BookingQuestion> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at')->orderBy('position')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function choiceList(): array
    {
        return array_values(array_filter(array_map(
            fn ($choice): string => trim(mb_substr((string) $choice, 0, 120)),
            is_array($this->choices) ? $this->choices : [],
        ), fn (string $choice): bool => $choice !== ''));
    }

    /**
     * @return list<int>
     */
    public function serviceIdList(): array
    {
        return array_values(array_map('intval', is_array($this->service_ids) ? $this->service_ids : []));
    }

    public function appliesTo(?int $serviceId): bool
    {
        $ids = $this->serviceIdList();

        return $ids === [] || ($serviceId !== null && in_array($serviceId, $ids, true));
    }

    public function fieldName(): string
    {
        return 'q_'.$this->id;
    }
}
