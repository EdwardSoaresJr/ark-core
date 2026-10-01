<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Read model: what should this endpoint look like right now?
 *
 * Regeneration and serialization ship in PR2.
 */
class EndpointConfigurationProjection extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'communication_device_id',
        'inputs_fingerprint',
        'serialized_config',
        'builder',
        'format',
        'generated_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'builder' => EndpointProvisionBuilder::class,
            'format' => EndpointProvisionFormat::class,
            'generated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function communicationDevice(): BelongsTo
    {
        return $this->belongsTo(CommunicationDevice::class);
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    /**
     * @param  EloquentBuilder<self>  $query
     * @return EloquentBuilder<self>
     */
    public function scopeCurrent(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereNull('superseded_at');
    }
}
