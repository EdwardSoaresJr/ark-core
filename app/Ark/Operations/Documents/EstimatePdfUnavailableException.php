<?php

namespace App\Ark\Operations\Documents;

use RuntimeException;

class EstimatePdfUnavailableException extends RuntimeException
{
    public static function forRepairOrder(int $repairOrderId): self
    {
        return new self("Estimate PDF could not be prepared for repair order #{$repairOrderId}.");
    }
}
