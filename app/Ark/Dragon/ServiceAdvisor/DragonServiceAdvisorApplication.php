<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DragonServiceAdvisorApplication extends Model
{
    protected $table = 'dragon_service_advisor_applications';

    protected $fillable = [
        'public_id',
        'repair_order_id',
        'concern_id',
        'repair_order_line_id',
        'field',
        'original_text',
        'proposal_text',
        'user_edited_proposal',
        'applied_text',
        'original_hash',
        'dragon_assist_request_id',
        'model_name',
        'mode',
        'applied_by_user_id',
        'applied_at',
        'reverted_by_user_id',
        'reverted_at',
    ];

    protected function casts(): array
    {
        return [
            'field' => ServiceAdvisorField::class,
            'mode' => ServiceAdvisorMode::class,
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            if (! filled($row->public_id)) {
                $row->public_id = (string) Str::uuid();
            }
        });
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'concern_id');
    }

    public function repairOrderLine(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLine::class, 'repair_order_line_id');
    }

    public function assistRequest(): BelongsTo
    {
        return $this->belongsTo(DragonAssistRequest::class, 'dragon_assist_request_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by_user_id');
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null && $this->reverted_at === null;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
