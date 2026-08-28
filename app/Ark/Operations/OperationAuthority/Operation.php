<?php

namespace App\Ark\Operations\OperationAuthority;

use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Foundational authority: owns Operation Class. Nothing else.
 *
 * Authorities may begin life with a single field if that field is the
 * operational truth they own. Richness comes from operational pressure, not imagination.
 */
class Operation extends Model
{
    protected $table = 'operations';

    protected $fillable = [
        'code',
        'name',
        'operation_class_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'operation_class_id' => 'integer',
        ];
    }

    public function operationClass(): BelongsTo
    {
        return $this->belongsTo(OperationClass::class, 'operation_class_id');
    }

    /**
     * The only question this authority answers for consumers.
     */
    public function operationClassKey(): string
    {
        $this->loadMissing('operationClass');

        if ($this->operationClass === null) {
            throw new RuntimeException("Operation [{$this->id}] has no Operation Class.");
        }

        return (string) $this->operationClass->key;
    }

    /**
     * Transitional loader: map the Labor Categories shop default onto an Operation.
     * Does not mean Operation Authority owns the default — Labor Categories does.
     *
     * TODO(migration): Remove once every labor line carries operation_id.
     */
    public static function forShopDefaultLaborCategory(?ShopSettings $settings = null): self
    {
        $settings ??= ShopSettings::current();
        $code = $settings->defaultLaborCategory()['key'] ?? null;

        if (! filled($code)) {
            throw new RuntimeException('Shop default labor category is not configured.');
        }

        return static::forLine(null, (string) $code);
    }

    /**
     * Load the Operation for an estimate line.
     *
     * Target end state: every labor line has operation_id → Operation. Nothing else.
     *
     * TODO(migration): Remove $code lookup and shop-default fallback once
     * repair_order_lines.operation_id is universal on labor lines.
     */
    public static function forLine(?int $operationId, ?string $code = null): self
    {
        if ($operationId !== null) {
            $operation = static::query()
                ->with('operationClass')
                ->whereKey($operationId)
                ->where('is_active', true)
                ->first();

            if ($operation !== null) {
                return $operation;
            }

            throw new RuntimeException("Active Operation [{$operationId}] was not found.");
        }

        $normalized = filled($code) ? trim((string) $code) : null;

        if ($normalized !== null) {
            $operation = static::query()
                ->with('operationClass')
                ->where('code', $normalized)
                ->where('is_active', true)
                ->first();

            if ($operation !== null) {
                return $operation;
            }

            throw new RuntimeException("Active Operation with code [{$normalized}] was not found.");
        }

        $fallbackCode = ShopSettings::current()->defaultLaborCategory()['key'] ?? null;

        if (! filled($fallbackCode)) {
            throw new RuntimeException('Shop default labor category is not configured.');
        }

        $fallback = static::query()
            ->with('operationClass')
            ->where('code', (string) $fallbackCode)
            ->where('is_active', true)
            ->first();

        if ($fallback !== null) {
            return $fallback;
        }

        throw new RuntimeException(
            "Shop default labor category [{$fallbackCode}] has no active Operation. Fix Labor Categories configuration.",
        );
    }
}
