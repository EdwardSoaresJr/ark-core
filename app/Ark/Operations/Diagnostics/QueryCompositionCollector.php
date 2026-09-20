<?php

namespace App\Ark\Operations\Diagnostics;

use Illuminate\Support\Facades\DB;

final class QueryCompositionCollector
{
    /** @var list<array{sql: string, bindings: array<int|string, mixed>, trace: list<array<string, mixed>>}> */
    private array $queries = [];

    public function measure(callable $callback): QueryCompositionReport
    {
        $this->queries = [];

        DB::flushQueryLog();
        DB::enableQueryLog();

        $listener = function (string $sql, array $bindings, float $time): void {
            $this->queries[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40),
            ];
        };

        DB::listen(function ($query) use ($listener): void {
            $listener($query->sql, $query->bindings, $query->time);
        });

        $callback();

        return $this->reportFromCollectedQueries();
    }

    public function reportFromQueryLog(): QueryCompositionReport
    {
        $this->queries = array_map(
            fn (array $entry): array => [
                'sql' => $entry['query'],
                'bindings' => $entry['bindings'],
                'trace' => [],
            ],
            DB::getQueryLog(),
        );

        return $this->reportFromCollectedQueries();
    }

    private function reportFromCollectedQueries(): QueryCompositionReport
    {
        $counts = [];
        $samples = [];
        $subcounts = [];
        $getMutations = [];
        $updateQueries = 0;

        foreach ($this->queries as $entry) {
            $category = $this->categorize($entry['sql'], $entry['trace']);
            $counts[$category] = ($counts[$category] ?? 0) + 1;

            $subcategory = $this->subcategoryFor($category, $entry['sql'], $entry['trace']);

            if ($subcategory !== null) {
                $subcounts[$category][$subcategory] = ($subcounts[$category][$subcategory] ?? 0) + 1;
                $sampleKey = $category.'/'.$subcategory;
            } else {
                $sampleKey = $category;
            }

            if ($this->isRepairOrderLineUpdate($entry['sql'])) {
                $updateQueries++;
            }

            if ($mutation = QueryCompositionGetMutation::classify($entry['sql'], $entry['trace'])) {
                $getMutations[] = $mutation;
            }

            if (($samples[$sampleKey] ?? []) === [] || count($samples[$sampleKey] ?? []) < 2) {
                $samples[$sampleKey][] = $this->summarizeSql($entry['sql']);
            }
        }

        return new QueryCompositionReport(
            totalQueries: count($this->queries),
            counts: $counts,
            samples: $samples,
            updateQueries: $updateQueries,
            subcounts: $subcounts,
            getMutations: $getMutations,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    private function subcategoryFor(string $category, string $sql, array $trace): ?string
    {
        return match ($category) {
            'FrameworkMisc' => $this->frameworkMiscSubcategory($sql, $trace),
            'ViewComposers' => $this->viewComposersSubcategory($sql, $trace),
            'FinancialPresenter' => $this->financialPresenterSubcategory($sql, $trace),
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    private function traceSignature(array $trace): string
    {
        return implode("\n", array_map(
            static fn (array $frame): string => ($frame['class'] ?? '').' '.($frame['file'] ?? ''),
            $trace,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    private function categorize(string $sql, array $trace): string
    {
        foreach ($trace as $frame) {
            $file = (string) ($frame['file'] ?? '');

            if ($file === '') {
                continue;
            }

            if (str_contains($file, 'resources/views/')) {
                $viewCategory = $this->categoryForView($file);

                if ($viewCategory !== null) {
                    return $viewCategory;
                }
            }

            if (! str_contains($file, '/app/Ark/')) {
                continue;
            }

            $classCategory = $this->categoryForClass($file);

            if ($classCategory !== null) {
                return $classCategory;
            }
        }

        return $this->categoryForTable($sql) ?? 'FrameworkMisc';
    }

    private function categoryForView(string $file): ?string
    {
        return match (true) {
            str_contains($file, 'repair-order-lifecycle-panel') => 'LifecycleProjection',
            str_contains($file, 'repair-order-lifecycle-select') => 'LifecycleControls',
            str_contains($file, 'repair-order-rail-tab-comms') => 'CommunicationTimeline',
            str_contains($file, 'repair-order-rail-tab-portal') => 'Portal',
            str_contains($file, 'operational-identity-band') => 'IdentityPresenters',
            str_contains($file, 'financial-rail') => 'FinancialPresenter',
            str_contains($file, 'financial-payment-strip') => 'FinancialPresenter',
            str_contains($file, 'repair-order-rail-tab-auth') => 'Authorization',
            str_contains($file, 'advisor-work-context-panel') => 'AdvisorWork',
            str_contains($file, 'repair-order-worksheet-collaboration') => 'Concurrency',
            str_contains($file, 'repair-order-concern-work-section'),
            str_contains($file, 'repair-order-line'),
            str_contains($file, '_line-composition') => 'ConcernsAndLines',
            str_contains($file, 'resources/views/operations/repair-orders/show.blade.php') => 'BladeMisc',
            default => null,
        };
    }

    private function categoryForClass(string $file): ?string
    {
        $rules = [
            'LifecycleProjection' => [
                'RepairOrderLifecycleProjection.php',
            ],
            'CommunicationTimeline' => [
                'CustomerHubCommsTimeline.php',
                'ConversationTimeline.php',
                'CallSessionTimeline.php',
                'CommunicationEventRecorder.php',
            ],
            'Documents' => [
                'EstimateDocument.php',
                'EstimateDocumentService.php',
                'InvoiceSnapshotBuilder.php',
                'RepairOrderPortalActivity.php',
            ],
            'FinancialPresenter' => [
                'RepairOrderFinancialPresenter.php',
                'RepairOrderDefaultDepositCalculator.php',
                'EstimateTotalsCalculator.php',
                'BalanceDueCalculator.php',
                'RepairOrderCloseoutAuthority.php',
                'RepairOrderPosting.php',
            ],
            'Authorization' => [
                'RecordCustomerAuthorizationAction.php',
                'RevokeCustomerAuthorizationAction.php',
            ],
            'AdvisorWork' => [
                'AdvisorWorkProjection.php',
            ],
            'IdentityPresenters' => [
                'OperationalIdentityPresenter.php',
                'LaborLinePresenter.php',
            ],
            'Posture' => [
                'RepairOrderPosture.php',
            ],
            'LifecycleControls' => [
                'RepairOrderLifecycleTransition.php',
                'RepairOrderLifecycleSelectProjection.php',
                'RepairOrderLifecycleSelectCache.php',
                'RepairOrderStatusCatalog.php',
            ],
            'Concurrency' => [
                'RepairOrderConcurrency.php',
                'RepairOrderEstimateVersion.php',
                'RepairOrderWorksheetSession.php',
                'RepairOrderWorksheetPresence.php',
            ],
            'ShopSettings' => [
                'ShopSettings.php',
                'ShopIntegrationCredentials.php',
                'ShopDisplayTimezone.php',
            ],
            'Staff' => [
                'SoloShopOperations.php',
            ],
            'ControllerEagerLoad' => [
                'RepairOrderShowController.php',
            ],
            'ConcernsAndLines' => [
                'RepairOrderConcern.php',
                'RepairOrderLine.php',
                'RepairOrderWorkGroup.php',
            ],
            'Portal' => [
                'PortalEstimatePage.php',
            ],
            'PartsCatalog' => [
                'NotConfiguredPartsCatalogLauncher.php',
                'LaborGuideLauncher.php',
            ],
            'AuthorizationChecks' => [
                'LearnArkTrainingGate.php',
                'ArkAuthorization',
            ],
            'ViewComposers' => [
                'WorkspaceTabBootEnricher.php',
                'WorkspaceTabSupport.php',
                'WorkspaceTabActivityResolver.php',
                'LearnArkProgressResolver.php',
            ],
        ];

        foreach ($rules as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($file, $needle)) {
                    return $category;
                }
            }
        }

        if (str_contains($file, 'RepairOrder.php')) {
            return 'Posture';
        }

        return null;
    }

    private function categoryForTable(string $sql): ?string
    {
        $normalized = strtolower($sql);

        return match (true) {
            str_contains($normalized, ' operational_events ') => 'LifecycleProjection',
            str_contains($normalized, ' communication_events ') => 'CommunicationTimeline',
            str_contains($normalized, ' estimate_documents ') => 'Documents',
            str_contains($normalized, ' repair_order_ledger_entries ') => 'FinancialPresenter',
            str_contains($normalized, ' approval_events ') => 'Authorization',
            str_contains($normalized, ' repair_order_lines ') => 'ConcernsAndLines',
            str_contains($normalized, ' repair_order_concerns ') => 'ConcernsAndLines',
            str_contains($normalized, ' shop_settings ') => 'ShopSettings',
            str_contains($normalized, ' advisor_work_items ') => 'AdvisorWork',
            str_contains($normalized, ' users ') => 'Staff',
            str_contains($normalized, ' permissions ')
                || str_contains($normalized, ' roles ')
                || str_contains($normalized, ' model_has_roles ') => 'AuthorizationChecks',
            default => null,
        };
    }

    private function isRepairOrderLineUpdate(string $sql): bool
    {
        return (bool) preg_match('/update\s+[`"]?repair_order_lines[`"]?/i', $sql);
    }

    private function summarizeSql(string $sql): string
    {
        $singleLine = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);

        return strlen($singleLine) > 140
            ? substr($singleLine, 0, 137).'...'
            : $singleLine;
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    private function frameworkMiscSubcategory(string $sql, array $trace): string
    {
        $normalizedSql = strtolower($sql);
        $traceSignature = $this->traceSignature($trace);

        if ($this->sqlReferencesTable($normalizedSql, ['permissions', 'roles', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'])
            || str_contains($traceSignature, 'Spatie\\Permission')) {
            return 'Permissions';
        }

        if (str_contains($traceSignature, 'Illuminate\\Auth\\Access\\Gate')
            || str_contains($traceSignature, 'AuthorizesRequests')
            || str_contains($traceSignature, 'ChecksAuthorization')
            || str_contains($traceSignature, 'AuthorizationException')) {
            return 'PolicyChecks';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['learn_completions'])) {
            return 'LearnTraining';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['ro_statuses', 'ro_status_transitions', 'ro_status_variants', 'ro_status_transition_roles'])) {
            return 'StatusCatalog';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['call_sessions'])) {
            return 'Telephony';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['users']) && str_contains($normalizedSql, 'last_seen_at')) {
            return 'Presence';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['sessions'])
            || str_contains($traceSignature, 'SessionGuard')
            || str_contains($traceSignature, 'StartSession')
            || str_contains($traceSignature, 'AuthenticateSession')) {
            return 'Auth';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['repair_orders'])
            && (str_contains($traceSignature, 'ImplicitRouteBinding')
                || str_contains($traceSignature, 'SubstituteBindings'))) {
            return 'RouteBinding';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['users'])) {
            return 'StaffLookup';
        }

        foreach ($trace as $frame) {
            $file = (string) ($frame['file'] ?? '');

            if (str_contains($file, 'resources/views/') && $this->categoryForView($file) === null) {
                return 'BladeIncludes';
            }
        }

        if ($this->sqlReferencesTable($normalizedSql, ['cache'])) {
            return 'Cache';
        }

        if (str_contains($traceSignature, 'Illuminate\\')) {
            return 'LaravelFramework';
        }

        return 'Misc';
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    private function viewComposersSubcategory(string $sql, array $trace): string
    {
        $normalizedSql = strtolower($sql);
        $traceSignature = $this->traceSignature($trace);

        if ($this->sqlReferencesTable($normalizedSql, ['learn_completions'])
            || str_contains($traceSignature, 'LearnArkProgressResolver')
            || str_contains($traceSignature, 'LearnTrainingShellProjection')) {
            return 'LearnTrainingShell';
        }

        if (str_contains($traceSignature, 'WorkspaceTabActivityResolver')) {
            return 'WorkspaceTabSignals';
        }

        if (str_contains($traceSignature, 'enrichRepairOrderBoot')
            || ($this->sqlReferencesTable($normalizedSql, ['repair_orders'])
                && str_contains($traceSignature, 'WorkspaceTabSupport'))) {
            return 'WorkspaceTabRepairOrder';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['customers'])
            && str_contains($traceSignature, 'WorkspaceTabSupport')) {
            return 'WorkspaceTabCustomer';
        }

        if ($this->sqlReferencesTable($normalizedSql, ['vehicles'])
            && str_contains($traceSignature, 'WorkspaceTabSupport')) {
            return 'WorkspaceTabVehicle';
        }

        if (str_contains($traceSignature, 'intakeTabTitle')
            || str_contains($traceSignature, 'intakeSubtitle')
            || str_contains($traceSignature, 'enrichIntakeBoot')) {
            return 'WorkspaceTabIntake';
        }

        if (str_contains($traceSignature, 'AppServiceProvider.php')) {
            return 'LayoutShell';
        }

        return 'Misc';
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    private function financialPresenterSubcategory(string $sql, array $trace): string
    {
        $traceSignature = $this->traceSignature($trace);
        $normalizedSql = strtolower($sql);

        return match (true) {
            str_contains($traceSignature, 'BalanceDueCalculator')
                || str_contains($traceSignature, 'RepairOrderBalanceProjection') => 'BalanceDue',
            str_contains($traceSignature, 'RepairOrderDefaultDepositCalculator') => 'Deposits',
            str_contains($traceSignature, 'EstimateTotalsCalculator') => 'Totals',
            str_contains($traceSignature, 'RepairOrderCloseoutAuthority') => 'Closeout',
            str_contains($traceSignature, 'RepairOrderPosting') => 'Posting',
            $this->sqlReferencesTable($normalizedSql, ['repair_order_ledger_entries']) => 'Ledger',
            default => 'PresenterCore',
        };
    }

    /**
     * @param  list<string>  $tables
     */
    private function sqlReferencesTable(string $normalizedSql, array $tables): bool
    {
        foreach ($tables as $table) {
            if (preg_match('/[`\'"]'.$table.'[`\'"]/', $normalizedSql) === 1) {
                return true;
            }

            if (str_contains($normalizedSql, ' '.$table.' ')) {
                return true;
            }
        }

        return false;
    }
}
