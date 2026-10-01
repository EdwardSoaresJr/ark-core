<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evidence extends Model
{
    use SoftDeletes;

    protected $table = 'evidence';

    protected $fillable = [
        'repair_order_id',
        'type',
        'source',
        'storage_path',
        'content_type',
        'original_name',
        'byte_size',
        'uploaded_by_user_id',
        'taken_at',
        'caption',
        'visibility',
        'shared_at',
        'first_customer_viewed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'source' => EvidenceSource::class,
            'visibility' => EvidenceVisibility::class,
            'taken_at' => 'datetime',
            'shared_at' => 'datetime',
            'first_customer_viewed_at' => 'datetime',
            'byte_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EvidenceAttachment::class);
    }

    public function visibilityHistory(): HasMany
    {
        return $this->hasMany(EvidenceVisibilityHistory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->where('visibility', EvidenceVisibility::Shared->value);
    }

    public function isActive(): bool
    {
        return $this->deleted_at === null;
    }

    public function isCustomerFacing(): bool
    {
        return $this->isActive()
            && $this->visibility instanceof EvidenceVisibility
            && $this->visibility->isCustomerFacing();
    }

    public function isImage(): bool
    {
        return $this->type === EvidenceType::Photo;
    }

    public function isVideo(): bool
    {
        return $this->type === EvidenceType::Video;
    }

    public function isPdf(): bool
    {
        return $this->type === EvidenceType::Pdf;
    }
}
