<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'repair_order_concern_id',
    'title',
    'position',
    'owner_type',
    'owner_user_id',
    'status',
    'latest_update',
    'created_from_template_id',
])]
class RepairOrderWorkGroup extends Model
{
    protected $touches = ['concern'];

    protected $table = 'repair_order_work_groups';

    protected function casts(): array
    {
        return [
            'owner_type' => RepairActionOwnerType::class,
            'status' => RepairActionStatus::class,
        ];
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => RepairOrderFreeText::normalize($value),
            set: fn (?string $value): string => RepairOrderFreeText::normalize($value),
        );
    }

    protected function latestUpdate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => self::normalizeLatestUpdate($value),
            set: fn (?string $value): ?string => self::normalizeLatestUpdate($value),
        );
    }

    public static function normalizeLatestUpdate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));

        return $normalized === '' ? null : $normalized;
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_user_id');
    }

    public function ownershipEvents(): HasMany
    {
        return $this->hasMany(RepairActionOwnershipEvent::class, 'repair_order_work_group_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepairOrderLine::class)
            ->orderByRaw(RepairOrderLineWorksheetOrder::sqlCaseExpression())
            ->orderBy('id');
    }

    public function createdFromTemplate(): BelongsTo
    {
        return $this->belongsTo(\App\Ark\Operations\WorkTemplates\WorkTemplate::class, 'created_from_template_id');
    }

    public function hasOwner(): bool
    {
        return $this->owner_type === RepairActionOwnerType::Technician
            && $this->owner_user_id !== null;
    }

    public function isOwnedByUserId(int $userId): bool
    {
        return $this->hasOwner() && (int) $this->owner_user_id === $userId;
    }

    public function hasLaborAnchor(): bool
    {
        return $this->lines->contains(
            fn (RepairOrderLine $line): bool => $line->type->isLabor()
                && $line->shouldDisplayOnEstimateWorksheet(),
        );
    }

    /**
     * Labor or PACKAGE can receive attached parts (e.g. extra oil quarts beyond package).
     */
    public function hasPartsAttachAnchor(): bool
    {
        return $this->lines->contains(
            fn (RepairOrderLine $line): bool => ($line->type->isLabor() || $line->type->isPackage())
                && $line->shouldDisplayOnEstimateWorksheet(),
        );
    }

    /**
     * @return list<RepairOrderLineType>
     */
    public function allowedComposerLineTypes(): array
    {
        if ($this->hasPartsAttachAnchor()) {
            $types = [
                RepairOrderLineType::Part,
                RepairOrderLineType::Sublet,
                RepairOrderLineType::Note,
                RepairOrderLineType::Fee,
            ];

            // Additional labor under the same repair is normal (Remove / Install).
            // Package-only anchors (engine oil) omit Labor — hours live on the package.
            if ($this->hasLaborAnchor()) {
                array_unshift($types, RepairOrderLineType::Labor);
            }

            return $types;
        }

        return [
            RepairOrderLineType::Labor,
            RepairOrderLineType::Note,
            RepairOrderLineType::Sublet,
        ];
    }
}
