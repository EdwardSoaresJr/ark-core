<?php

namespace Database\Seeders;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderOperationalDates;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Throwable;

class DemoReportingSeeder extends Seeder
{
    public function run(): void
    {
        config(['broadcasting.default' => 'null']);

        $settings = ShopSettings::current();
        $timezone = (string) ($settings->shop_timezone ?: 'America/Denver');
        $advisor = User::query()->where('email', 'advisor@ark.test')->first()
            ?? User::query()->where('email', 'demo@arksms.com')->first();
        $technician = User::query()->where('email', 'tech@ark.test')->first();

        if ($technician !== null && $technician->labor_cost_cents === null) {
            $technician->forceFill(['labor_cost_cents' => 4500])->save();
        }

        $days = $this->weekdays($timezone);
        if ($days === [] || $advisor === null) {
            return;
        }

        $snapshots = app(InvoiceSnapshotBuilder::class);
        $ledger = app(RecordLedgerEntryAction::class);
        $balanceDue = app(BalanceDueCalculator::class);
        $dates = app(RepairOrderOperationalDates::class);
        $methods = [PaymentMethod::Card, PaymentMethod::Cash, PaymentMethod::Check];
        $hours = [9, 10, 11, 13, 14, 15, 16];

        $ids = RepairOrder::query()
            ->where('status', RepairOrderStatus::Closed->value)
            ->whereNull('posted_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $index => $id) {
            $order = RepairOrder::query()->find($id);
            if ($order === null || $order->posted_at !== null) {
                continue;
            }

            $day = $days[$index % count($days)];
            $hour = $hours[$index % count($hours)];
            $postedAt = Carbon::create($day->year, $day->month, $day->day, $hour, ($index % 4) * 10, 0, $timezone)->utc();
            $openedAt = $postedAt->copy()->subDay()->setTime(8, 0);
            $floor = OperationalReportDateScope::trustworthyDataStartsAt();
            if ($openedAt->lessThan($floor)) {
                $openedAt = $postedAt->copy()->subHour();
            }

            if ($technician !== null && $order->assigned_technician_id === null) {
                $order->assigned_technician_id = $technician->id;
            }

            $order->forceFill([
                'assigned_technician_id' => $order->assigned_technician_id,
                'opened_at' => $order->opened_at ?? $openedAt,
                'closed_at' => $order->closed_at ?? $postedAt,
            ])->save();

            try {
                $snapshot = $snapshots->build($order->fresh(), $advisor);
            } catch (Throwable) {
                continue;
            }

            if (InvoiceSnapshotBuilder::invoiceTotalCents($snapshot) <= 0) {
                continue;
            }

            $invoice = EstimateDocument::query()
                ->where('repair_order_id', $order->id)
                ->where('document_type', FinancialDocumentType::Invoice->value)
                ->where('status', '!=', InvoiceStatus::Voided->value)
                ->first();

            if ($invoice === null) {
                $invoice = EstimateDocument::query()->create([
                    'repair_order_id' => $order->id,
                    'document_type' => FinancialDocumentType::Invoice->value,
                    'document_number' => 1,
                    'snapshot_json' => $snapshot,
                    'status' => InvoiceStatus::Issued->value,
                    'issued_at' => $postedAt,
                    'generated_at' => $postedAt,
                    'created_by' => $advisor->id,
                    'needs_pdf_refresh' => false,
                ]);
            }

            $amount = $balanceDue->forRepairOrder($order->fresh())->balanceDueCents;
            if ($amount > 0) {
                $ledger->recordPayment(
                    $order->fresh(),
                    $amount,
                    $methods[$index % count($methods)],
                    $advisor,
                    recordedAt: $postedAt,
                );
            }

            $dates->applyPost($order->fresh(), $postedAt);
            $order->fresh()->forceFill([
                'paid_at' => $postedAt,
                'payment_status' => RepairOrderPaymentStatus::Paid,
                'closed_at' => $order->closed_at ?? $postedAt,
            ])->save();

            unset($invoice);
        }
    }

    /**
     * @return list<Carbon>
     */
    private function weekdays(string $timezone): array
    {
        $start = OperationalReportDateScope::trustworthyDataStartsAt()->timezone($timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();
        if ($start->greaterThan($today)) {
            $start = $today->copy();
        }

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($today)) {
            if ($cursor->isWeekday()) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $days;
    }
}
