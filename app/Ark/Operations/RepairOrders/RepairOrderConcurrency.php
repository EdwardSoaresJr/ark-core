<?php

namespace App\Ark\Operations\RepairOrders;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RepairOrderConcurrency
{
    public const FIELD = 'opened_estimate_version';

    private bool $ownsTransaction = false;

    private ?Closure $afterLockHeld = null;

    public function openedVersion(RepairOrder $repairOrder): int
    {
        return (int) $repairOrder->estimate_version;
    }

    /**
     * Lock the repair order, then accept or reject the opened version.
     *
     * The lock stays until finish() so the mutation and version bump commit
     * together. A second writer blocks on this row and then sees the new version.
     */
    public function guard(Request $request, RepairOrder $repairOrder): void
    {
        $this->acquire($repairOrder);
        $this->assertOpenedVersion($request, $repairOrder);
        $this->runAfterLockHeld($repairOrder);
    }

    /**
     * Check the opened version under a row lock and release it before returning.
     *
     * Payment capture and outbound send must not keep this transaction open:
     * capture commits its attempt before the provider call, and a send cannot
     * be rolled back once it has left the shop.
     */
    public function guardWithoutHolding(Request $request, RepairOrder $repairOrder): void
    {
        $openedVersion = $request->input(self::FIELD);

        if ($openedVersion === null || $openedVersion === '') {
            return;
        }

        DB::transaction(function () use ($request, $repairOrder, $openedVersion): void {
            $locked = $this->lock($repairOrder);

            if ((int) $openedVersion === (int) $locked->estimate_version) {
                return;
            }

            throw new HttpResponseException(
                (new RepairOrderEstimateConflictException($locked))->render($request),
            );
        });
    }

    public function setAfterLockHeld(?callable $callback): void
    {
        $this->afterLockHeld = $callback === null ? null : Closure::fromCallable($callback);
    }

    public function finish(bool $commit): void
    {
        if (! $this->ownsTransaction) {
            return;
        }

        if (DB::transactionLevel() < 1) {
            $this->ownsTransaction = false;
            $this->afterLockHeld = null;

            return;
        }

        try {
            if ($commit) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } finally {
            $this->ownsTransaction = false;
            $this->afterLockHeld = null;
        }
    }

    public function abandonIfStillHolding(): void
    {
        $this->finish(false);
    }

    private function acquire(RepairOrder $repairOrder): void
    {
        if (! $this->ownsTransaction) {
            DB::beginTransaction();
            $this->ownsTransaction = true;
        }

        $this->lock($repairOrder);
    }

    private function lock(RepairOrder $repairOrder): RepairOrder
    {
        $locked = RepairOrder::query()->whereKey($repairOrder->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            return $repairOrder;
        }

        $repairOrder->setRawAttributes($locked->getAttributes(), true);
        $repairOrder->syncOriginal();

        return $repairOrder;
    }

    private function assertOpenedVersion(Request $request, RepairOrder $repairOrder): void
    {
        $openedVersion = $request->input(self::FIELD);

        if ($openedVersion === null || $openedVersion === '') {
            return;
        }

        if ((int) $openedVersion === (int) $repairOrder->estimate_version) {
            return;
        }

        $this->finish(false);

        throw new HttpResponseException(
            (new RepairOrderEstimateConflictException($repairOrder))->render($request),
        );
    }

    private function runAfterLockHeld(RepairOrder $repairOrder): void
    {
        if ($this->afterLockHeld === null) {
            return;
        }

        try {
            ($this->afterLockHeld)($repairOrder);
        } catch (Throwable $exception) {
            $this->finish(false);

            throw $exception;
        }
    }
}
