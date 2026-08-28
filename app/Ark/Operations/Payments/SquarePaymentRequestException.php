<?php

namespace App\Ark\Operations\Payments;

use RuntimeException;

/** Operational Square API failure — advisor-facing, not an application defect. */
final class SquarePaymentRequestException extends RuntimeException {}
