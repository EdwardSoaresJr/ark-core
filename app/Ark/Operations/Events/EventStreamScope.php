<?php

namespace App\Ark\Operations\Events;

/**
 * Scoped event stream membership — mechanical projection of E0b.
 */
enum EventStreamScope: string
{
    case Customer = 'customer';
    case RepairOrder = 'repair_order';
    case ShopFeed = 'shop_feed';
    case Vehicle = 'vehicle';
    case Operator = 'operator';
    case Audit = 'audit';
}
