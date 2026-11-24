<?php

declare(strict_types=1);

$f = __DIR__.'/logs/junit.xml';
$x = simplexml_load_file($f);
if ($x === false) {
    fwrite(STDERR, "Cannot parse junit\n");
    exit(1);
}

$files = [
    'AuthorizedCentDriftTest.php',
    'ApprovedWorkChangesAfterAuthorizationTest.php',
    'AuthorizationIntegrityTest.php',
    'EstimateFinancialAuthorityTest.php',
    'Core747295FinancialIntegrityTest.php',
    'LaborMatrixLifecycleTest.php',
    'PartsMatrixAndTaxForwardOnlyTest.php',
    'ShopFeeFlatDollarTest.php',
    'ShopFeePerLineAllocationTest.php',
    'ShopFeeAppliesToTest.php',
    'PaymentCaptureFoundationTest.php',
    'FinancialIntegrityCaptureRepairTest.php',
    'CommercialPricingProfileForwardOnlyTest.php',
    'PricingEngineSafetyTest.php',
    'UnifiedBillingWarrantyTermsTest.php',
    'LaborAuthorityTest.php',
    'LaborRateAuthorityTest.php',
    'AccountsReceivablePaymentAllocationTest.php',
    'FinancialCoreAuthorityTest.php',
    'WorksheetContinuityAuthorizationTest.php',
    'WorksheetContinuitySettlementTest.php',
    'EstimatePricingFloorIntegrityTest.php',
    'CustomerFacingLaborAuthorityBoundaryTest.php',
    'BillingClassSettingsConsistencyTest.php',
    'ConcernBillingRepricingTest.php',
    'RepairOrderFinancialPresenterBalanceAxesTest.php',
    'FinancialPositionProjectionTest.php',
    'FinancialSubmissionIntentTest.php',
    'InvoiceDocumentViewTest.php',
    'InvoiceRevisionHistoryTest.php',
    'IssuedInvoicePdfPreservationTest.php',
    'AccountsReceivableCardCaptureTest.php',
    'PlatformStaffPaymentCaptureTest.php',
    'PaymentWorkspaceTest.php',
    'CaptureCompletionAfterCloseTest.php',
    'SettingsSeparationPhase1Test.php',
    'SettingsSeparationPhase2Test.php',
    'SettingsSeparationPhase3Test.php',
    'IntakeEntryTest.php',
    'VisitCheckInActionTest.php',
    'RepairOrderStatusCatalogTest.php',
    'RepairOrderCloseoutFinalPdfTest.php',
    'JobBoardListViewTest.php',
    'WorkboardCardGlanceTest.php',
    'AdvisorHomeBoardTest.php',
    'ConcurrencyOverlapTest.php',
];

$out = fopen(__DIR__.'/evidence/financial-suite-status.txt', 'w');
foreach ($files as $want) {
    $p = $fai = $s = 0;
    $names = [];
    foreach ($x->xpath('//testcase') ?: [] as $c) {
        $file = (string) ($c['file'] ?? '');
        if (! str_contains($file, $want)) {
            continue;
        }
        $name = (string) ($c['name'] ?? '');
        if (isset($c->skipped)) {
            $s++;
            continue;
        }
        if (isset($c->failure) || isset($c->error)) {
            $fai++;
            $names[] = $name;
        } else {
            $p++;
        }
    }
    $status = $fai > 0 ? 'FAIL' : (($p + $s) === 0 ? 'MISSING' : 'PASS');
    $line = sprintf("%-6s pass=%-3d fail=%-2d skip=%-2d %s\n", $status, $p, $fai, $s, $want);
    fwrite($out, $line);
    echo $line;
    foreach ($names as $n) {
        $l = "         FAIL: $n\n";
        fwrite($out, $l);
        echo $l;
    }
}

$total = $fail = $skip = $pass = 0;
foreach ($x->xpath('//testcase') ?: [] as $c) {
    $total++;
    if (isset($c->skipped)) {
        $skip++;
        continue;
    }
    if (isset($c->failure) || isset($c->error)) {
        $fail++;
    } else {
        $pass++;
    }
}
$s = $x->testsuite[0] ?? $x;
$summary = "\nOVERALL total=$total pass=$pass fail=$fail skip=$skip\n";
$summary .= "junit_attrs tests={$s['tests']} failures={$s['failures']} errors={$s['errors']} skipped={$s['skipped']} time={$s['time']} assertions={$s['assertions']}\n";
fwrite($out, $summary);
echo $summary;
fclose($out);

$msgOut = fopen(__DIR__.'/evidence/key-failure-messages.txt', 'w');
$watch = [
    'AdvisorHomeBoardTest',
    'RepairOrderOpenProfileTest',
    'RepairOrderFinancialPresenterBalanceAxesTest',
    'PartsMatrixTuneTest',
    'SendEstimateLinkTest',
    'RepairOrderLifecycleQueueTest',
    'DailyOperationsTablerTest',
    'OwnerCallIntelligenceTest',
    'DemoCompanionSurfaceTest',
    'CommunicationsQuickReplyTemplatesTest',
];
foreach ($x->xpath('//testcase') ?: [] as $c) {
    $file = (string) $c['file'];
    $hit = false;
    foreach ($watch as $w) {
        if (str_contains($file, $w)) {
            $hit = true;
            break;
        }
    }
    if (! $hit || (! isset($c->failure) && ! isset($c->error))) {
        continue;
    }
    $msg = (string) ($c->failure ?? $c->error);
    $msg = preg_replace('/\s+/', ' ', substr($msg, 0, 500));
    $line = (string) $c['name']."\n  ".$msg."\n\n";
    fwrite($msgOut, $line);
    echo $line;
}
fclose($msgOut);
