<?php

namespace App\Ark\Dragon\Assist;

use App\Ark\Dragon\Bridge\DragonBridgeDispatcher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business entry: persist assist request, then hand off to transport.
 * Callers never touch sockets.
 */
final class RequestDragonAssistAction
{
    public function __construct(
        private readonly DragonBridgeDispatcher $dispatcher = new DragonBridgeDispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        DragonAssistTaskType $taskType,
        array $payload,
        ?int $repairOrderId = null,
        ?int $vehicleId = null,
        ?User $actor = null,
    ): DragonAssistRequest {
        if (! in_array($taskType->value, DragonAssistTaskType::allowlist(), true)) {
            throw new InvalidArgumentException('Unknown Dragon assist task type.');
        }

        $this->assertNoCustomerPii($payload);

        $request = DB::transaction(function () use ($taskType, $payload, $repairOrderId, $vehicleId, $actor): DragonAssistRequest {
            return DragonAssistRequest::query()->create([
                'task_type' => $taskType,
                'status' => DragonAssistStatus::Pending,
                'payload_json' => $payload,
                'repair_order_id' => $repairOrderId,
                'vehicle_id' => $vehicleId,
                'requested_by_user_id' => $actor?->id,
            ]);
        });

        $this->dispatcher->dispatchEligible($request->fresh());

        return $request->fresh(['result']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoCustomerPii(array $payload): void
    {
        $encoded = strtolower(json_encode($payload) ?: '');
        foreach (['phone', 'email', 'address', 'ssn', 'card_number'] as $banned) {
            if (str_contains($encoded, '"'.$banned.'"')) {
                throw new InvalidArgumentException('Assist payload must not include customer PII fields.');
            }
        }
    }
}
