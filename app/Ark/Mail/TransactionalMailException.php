<?php

namespace App\Ark\Mail;

use RuntimeException;

final class TransactionalMailException extends RuntimeException
{
    public function __construct(public readonly TransactionalMailResult $result)
    {
        parent::__construct($result->operatorMessage());
    }
}
