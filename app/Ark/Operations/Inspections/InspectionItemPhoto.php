<?php

namespace App\Ark\Operations\Inspections;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItemPhoto extends Model
{
    protected $fillable = [
        'inspection_item_id',
        'purpose',
        'storage_path',
        'content_type',
        'original_name',
        'byte_size',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => InspectionPhotoPurpose::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function purposeLabel(): string
    {
        $purpose = $this->purpose;

        return $purpose instanceof InspectionPhotoPurpose
            ? $purpose->label()
            : (string) $purpose;
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->content_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->content_type, 'video/');
    }
}
