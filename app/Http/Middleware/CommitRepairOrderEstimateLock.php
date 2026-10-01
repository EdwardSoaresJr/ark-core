<?php

namespace App\Http\Middleware;

use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CommitRepairOrderEstimateLock
{
    public function __construct(private readonly RepairOrderConcurrency $concurrency) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->concurrency->finish(false);

            throw $exception;
        }

        $this->concurrency->finish($response->isSuccessful() || $response->isRedirection());

        return $response;
    }
}