<?php

namespace App\Ark\Dragon\Assist;

use App\Ark\Dragon\Bridge\DragonNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Explicit state machine for assist lifecycle. All transitions are idempotent.
 */
final class DragonAssistLifecycle
{
    /**
     * Claim a request for delivery to a node.
     *
     * Attempt count increments only on a real new claim (pending → dispatched,
     * or reassignment to a different node). Reconnect reconcile / re-broadcast
     * to the same node is idempotent and must not burn MAX_ATTEMPTS.
     */
    public function markDispatched(DragonAssistRequest $request, DragonNode $node): DragonAssistRequest
    {
        return DB::transaction(function () use ($request, $node): DragonAssistRequest {
            /** @var DragonAssistRequest $locked */
            $locked = DragonAssistRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            // Already claimed by this node — idempotent; never burn another attempt.
            if (in_array($locked->status, [DragonAssistStatus::Dispatched, DragonAssistStatus::Accepted], true)
                && (int) $locked->dragon_node_id === (int) $node->id) {
                return $locked;
            }

            // Do not steal an in-flight accept from another node.
            if ($locked->status === DragonAssistStatus::Accepted
                && $locked->dragon_node_id !== null
                && (int) $locked->dragon_node_id !== (int) $node->id) {
                return $locked;
            }

            $isNewClaim = $locked->status === DragonAssistStatus::Pending
                || ($locked->status === DragonAssistStatus::Dispatched
                    && $locked->dragon_node_id !== null
                    && (int) $locked->dragon_node_id !== (int) $node->id);

            if ($isNewClaim && $locked->attempt_count >= DragonAssistRequest::MAX_ATTEMPTS) {
                return $this->failLocked($locked, 'max_attempts', 'Maximum dispatch attempts exceeded.');
            }

            $attemptCount = $isNewClaim ? $locked->attempt_count + 1 : $locked->attempt_count;

            $locked->forceFill([
                'status' => DragonAssistStatus::Dispatched,
                'dragon_node_id' => $node->id,
                'dispatched_at' => $locked->dispatched_at ?? Carbon::now(),
                'attempt_count' => $attemptCount,
                'last_error' => null,
            ])->save();

            return $locked->fresh();
        });
    }

    public function markAccepted(DragonAssistRequest $request, DragonNode $node): DragonAssistRequest
    {
        return DB::transaction(function () use ($request, $node): DragonAssistRequest {
            /** @var DragonAssistRequest $locked */
            $locked = DragonAssistRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === DragonAssistStatus::Accepted
                || $locked->status === DragonAssistStatus::Completed
                || $locked->status === DragonAssistStatus::Failed) {
                return $locked;
            }

            if (! in_array($locked->status, [DragonAssistStatus::Pending, DragonAssistStatus::Dispatched], true)) {
                throw new RuntimeException('Assist request cannot be accepted from status '.$locked->status->value);
            }

            $locked->forceFill([
                'status' => DragonAssistStatus::Accepted,
                'dragon_node_id' => $node->id,
                'accepted_at' => $locked->accepted_at ?? Carbon::now(),
            ])->save();

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $resultJson
     */
    public function markCompleted(
        DragonAssistRequest $request,
        ?DragonNode $node,
        array $resultJson,
        ?string $modelName = null,
        ?string $modelVersion = null,
        ?string $knowledgeVersion = null,
        ?int $durationMs = null,
    ): DragonAssistRequest {
        return DB::transaction(function () use ($request, $node, $resultJson, $modelName, $modelVersion, $knowledgeVersion, $durationMs): DragonAssistRequest {
            /** @var DragonAssistRequest $locked */
            $locked = DragonAssistRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === DragonAssistStatus::Completed && $locked->result !== null) {
                return $locked->load('result');
            }

            if ($locked->status === DragonAssistStatus::Failed) {
                return $locked->load('result');
            }

            $locked->forceFill([
                'status' => DragonAssistStatus::Completed,
                'dragon_node_id' => $node?->id,
                'completed_at' => Carbon::now(),
                'last_error' => null,
            ])->save();

            DragonAssistResult::query()->updateOrCreate(
                ['dragon_assist_request_id' => $locked->id],
                [
                    'dragon_node_id' => $node?->id,
                    'result_json' => $resultJson,
                    'model_name' => $modelName,
                    'model_version' => $modelVersion,
                    'knowledge_version' => $knowledgeVersion,
                    'duration_ms' => $durationMs,
                ],
            );

            return $locked->fresh(['result']);
        });
    }

    public function markFailed(
        DragonAssistRequest $request,
        ?DragonNode $node,
        string $errorCode,
        string $errorMessage,
    ): DragonAssistRequest {
        return DB::transaction(function () use ($request, $node, $errorCode, $errorMessage): DragonAssistRequest {
            /** @var DragonAssistRequest $locked */
            $locked = DragonAssistRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked->load('result');
            }

            return $this->failLocked($locked, $errorCode, $errorMessage, $node);
        });
    }

    public function reclaimStaleAccepted(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $cutoff = $now->copy()->subSeconds(DragonAssistRequest::ACCEPT_TIMEOUT_SECONDS);

        $count = 0;

        DragonAssistRequest::query()
            ->where('status', DragonAssistStatus::Accepted->value)
            ->where('accepted_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (DragonAssistRequest $request) use (&$count): void {
                $node = $request->node;
                if ($node === null && $request->dragon_node_id !== null) {
                    $node = DragonNode::query()->find($request->dragon_node_id);
                }

                if ($node === null) {
                    $request->forceFill([
                        'status' => DragonAssistStatus::Failed,
                        'failed_at' => Carbon::now(),
                        'last_error' => 'stale_accepted: Accepted assist timed out without completion.',
                    ])->save();
                    $count++;

                    return;
                }

                $this->markFailed($request, $node, 'stale_accepted', 'Accepted assist timed out without completion.');
                $count++;
            });

        return $count;
    }

    private function failLocked(
        DragonAssistRequest $locked,
        string $errorCode,
        string $errorMessage,
        ?DragonNode $node = null,
    ): DragonAssistRequest {
        Log::info('dragon.assist.failed', [
            'request_id' => $locked->public_id,
            'error_code' => $errorCode,
        ]);

        $locked->forceFill([
            'status' => DragonAssistStatus::Failed,
            'dragon_node_id' => $node?->id ?? $locked->dragon_node_id,
            'failed_at' => Carbon::now(),
            'last_error' => substr($errorCode.': '.$errorMessage, 0, 500),
        ])->save();

        return $locked->fresh(['result']);
    }
}
