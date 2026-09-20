<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileIntakeCustomerProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Customers\CustomerSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileIntakeCustomerSearchController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileIntakeCustomerProjection $projection,
    ): JsonResponse {
        abort_unless($access->canPerformIntake($request->user()), 403);

        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json([
                'items' => [],
                'count' => 0,
            ]);
        }

        $customers = CustomerSearchQuery::matching($query, 12);

        return response()->json([
            'items' => $customers
                ->map(fn ($customer): array => $projection->searchResult($customer))
                ->values()
                ->all(),
            'count' => $customers->count(),
        ]);
    }
}
