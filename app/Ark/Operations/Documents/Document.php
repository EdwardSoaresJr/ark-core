<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documents authority — durable paperwork.
 *
 * Freeze:
 * - Documents preserve paperwork. They do not interpret it.
 * - A document exists once. Relationships determine where it appears.
 *   The physical document is never duplicated merely to satisfy a relationship.
 * - Documents may be presented by many surfaces but are authored once.
 * - Other authorities may reference documents; Documents never inherits their responsibilities.
 */
class Document extends Model
{
    use SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'customer_id',
        'repair_order_id',
        'type',
        'title',
        'description',
        'storage_path',
        'content_type',
        'original_name',
        'byte_size',
        'page_count',
        'source',
        'uploaded_by_user_id',
        'captured_at',
        'customer_visible',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'source' => DocumentSource::class,
            'byte_size' => 'integer',
            'page_count' => 'integer',
            'captured_at' => 'datetime',
            'customer_visible' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function isActive(): bool
    {
        return $this->deleted_at === null;
    }

    public function isPdf(): bool
    {
        return str_starts_with(strtolower($this->content_type), 'application/pdf');
    }

    public function isImage(): bool
    {
        return str_starts_with(strtolower($this->content_type), 'image/');
    }
}
