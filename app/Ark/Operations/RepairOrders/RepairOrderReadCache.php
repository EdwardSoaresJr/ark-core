<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Settings\ShopSettingsSchema;
use App\Ark\Runtime\DatabaseReadScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Remembers read-only repair-order lookups for the rest of this request.
 * A write to the underlying records drops the memory so the next read is fresh.
 */
final class RepairOrderReadCache
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function remember(string $key, callable $resolve): mixed
    {
        $key = DatabaseReadScope::key()."\0".$key;

        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        return $this->values[$key] = $resolve();
    }

    public function flush(): void
    {
        $this->values = [];
    }

    /**
     * Drops remembered repair-order rows at a request or queue-job boundary.
     * A long-running worker keeps this object, so the next unit of work must not see the last one.
     * Shop-settings column memory is not repair-order data. It stays for the process
     * and is checked against migration history on the next read.
     */
    public static function beginUnitOfWork(): void
    {
        ShopSettingsSchema::revalidate();

        if (app()->resolved(self::class)) {
            app(self::class)->flush();
        }
    }

    public static function registerFlushListeners(): void
    {
        $flush = function (): void {
            if (app()->resolved(self::class)) {
                app(self::class)->flush();
            }
        };

        foreach ([
            RepairOrderLedgerEntry::class,
            EstimateDocument::class,
            ApprovedWorkScope::class,
            ApprovalEvent::class,
            OperationalEvent::class,
            ConversationMessage::class,
            InspectionItem::class,
            InspectionItemPhoto::class,
            InspectionItemMeasurement::class,
            RepairOrderLine::class,
            PhoneSmsCapability::class,
        ] as $model) {
            /** @var class-string<Model> $model */
            $model::saved($flush);
            $model::deleted($flush);
        }
    }
}
