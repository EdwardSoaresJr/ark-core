<?php

namespace App\Ark\Operations\Inspections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'enabled',
        'is_default',
        'position',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(InspectionTemplateCategory::class)->orderBy('position')->orderBy('id');
    }

    public static function defaultEnabled(): ?self
    {
        return self::query()
            ->where('enabled', true)
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
