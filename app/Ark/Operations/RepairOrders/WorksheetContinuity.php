<?php

namespace App\Ark\Operations\RepairOrders;

final class WorksheetContinuity
{
    // Line cards, scope and line count, concern money, estimate totals, authorized dollars, and financial position.
    public const LINES = 'lines';

    // Concern decision, authorized dollars, financial position, and invoice posture.
    public const AUTHORIZATION = 'authorization';

    // Ledger balance, payment history, payment posture, financial position, and closeout eligibility.
    public const SETTLEMENT = 'settlement';

    public const IDENTITY = 'identity';

    // Repair order status.
    public const WORKFLOW = 'workflow';

    public const SESSION_SCOPE = 'continuity_scope';

    public const SESSION_CONCERN = 'continuity_concern_id';

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [
            self::LINES,
            self::AUTHORIZATION,
            self::SETTLEMENT,
            self::IDENTITY,
            self::WORKFLOW,
        ];
    }

    public static function declare(string ...$scopes): string
    {
        if ($scopes === []) {
            throw new \InvalidArgumentException('A continuity declaration needs a scope.');
        }

        $declared = [];

        foreach ($scopes as $scope) {
            if (! in_array($scope, self::scopes(), true)) {
                throw new \InvalidArgumentException('Unknown continuity scope ['.$scope.'].');
            }

            if (! in_array($scope, $declared, true)) {
                $declared[] = $scope;
            }
        }

        return implode(' ', $declared);
    }
}
