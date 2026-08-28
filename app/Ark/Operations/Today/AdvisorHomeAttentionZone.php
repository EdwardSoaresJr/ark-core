<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeAttentionZone
{
    /**
     * @param  list<AdvisorHomeAttentionRow>  $rows
     */
    public function __construct(
        public AdvisorHomeAttentionZoneKey $key,
        public string $label,
        public int $count,
        public array $rows,
    ) {}
}
