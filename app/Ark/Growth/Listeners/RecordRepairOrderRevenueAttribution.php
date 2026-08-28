<?php

namespace App\Ark\Growth\Listeners;

use App\Ark\Growth\Attribution\RevenueAttributionWriter;
use App\Ark\Growth\Contracts\Operations\RepairOrderClosedForGrowth;

final class RecordRepairOrderRevenueAttribution
{
    public function __construct(
        private readonly RevenueAttributionWriter $writer,
    ) {}

    public function handle(RepairOrderClosedForGrowth $event): void
    {
        try {
            $this->writer->fromRepairOrderClosed($event->payload);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
