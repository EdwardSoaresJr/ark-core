<?php

namespace App\Ark\Platform\Website;

use App\Ark\Platform\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WebsiteSite extends Model
{
    protected $table = 'website_sites';

    protected $fillable = [
        'platform_shop_id',
        'public_host',
        'writing_authority',
        'acknowledged_core_hash',
        'imported_at',
        'management_enabled',
    ];

    protected function casts(): array
    {
        return [
            'writing_authority' => WebsiteWritingAuthority::class,
            'imported_at' => 'datetime',
            'management_enabled' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'platform_shop_id');
    }

    public function draft(): HasOne
    {
        return $this->hasOne(WebsiteDraft::class);
    }

    public function managementEnabled(): bool
    {
        return (bool) $this->management_enabled
            && (bool) config('platform_website.management_enabled', true);
    }
}
