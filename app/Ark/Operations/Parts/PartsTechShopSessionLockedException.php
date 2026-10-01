<?php

namespace App\Ark\Operations\Parts;

use RuntimeException;

final class PartsTechShopSessionLockedException extends RuntimeException
{
    public function __construct(
        public readonly string $blockingCartReference,
        string $message,
    ) {
        parent::__construct($message);
    }
}
