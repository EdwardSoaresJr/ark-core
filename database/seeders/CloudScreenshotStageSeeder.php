<?php

namespace Database\Seeders;

use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationLinker;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use Throwable;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local/testing only — fictional shop floor for Cloud marketing screenshots.
 * Marker emails: cloud-shot+*@example.test · 555 phone numbers · RO shop numbers 4800+.
 */
class CloudScreenshotStageSeeder extends Seeder
{
    public const EMAIL_PREFIX = 'cloud-shot+';

    public const HERO_EMAIL = 'cloud-shot+sarah@example.test';

    /** Shop-facing hero RO number (mature shop sequence). */
    public const HERO_SHOP_NUMBER = 4847;

    public function __construct(
        private readonly EstimateTotalsCalculator $totals,
        private readonly EstimateDocumentService $documents,
        private readonly ConversationResolver $conversations,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationParticipantResolver $participants,
        private readonly ConversationLinker $linker,
    ) {}

    public function run(): void
    {
        $this->polishStaffNamesForScreenshots();

        $settings = ShopSettings::current();
        $callFlow = is_array($settings->telephony_call_flow) ? $settings->telephony_call_flow : [];
        // Capture RO/workboard without attention-gate redirects to Needs Attention.
        $callFlow['comms_attention_gate_enabled'] = false;

        $settings->update([
            'tax_enabled' => true,
            'tax_label' => 'Sales Tax',
            'default_tax_rate' => '8.250',
            'taxable_labor' => false,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'shop_fee_enabled' => true,
            'shop_fee_rate' => '5.000',
            'shop_fee_cap_cents' => 3500,
            'parts_matrix' => ShopSettings::DEFAULT_PARTS_MATRIX,
            'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
            // Clears “Customer portal payments are not enabled” on Quick Reply.
            'square_portal_pay_enabled' => true,
            'telephony_call_flow' => $callFlow,
        ]);

        $this->ensureShopNumberFloor(4800);

        DB::transaction(function (): void {
            $hero = $this->seedHeroRepairOrder();
            $this->seedHeroConversation($hero);
            $this->seedBoardCast();
            $this->closeLowNumberOpenBoardClutter();
        });
    }

    /** Local screenshot cast — realistic names, not “Demo Advisor / ARK Admin”. */
    private function polishStaffNamesForScreenshots(): void
    {
        User::query()->where('email', 'admin@ark.test')->update(['name' => 'Alex Rivera']);
        User::query()->where('email', 'advisor@ark.test')->update(['name' => 'Marcus Hale']);
        User::query()->where('email', 'tech@ark.test')->update(['name' => 'Jordan Lee']);

        // Stale participant labels from earlier Demo* seeds.
        ConversationParticipant::query()
            ->whereNotNull('user_id')
            ->where(function ($q): void {
                $q->where('display_name', 'like', '%Demo%')
                    ->orWhere('display_name', 'like', '%ARK Admin%');
            })
            ->get()
            ->each(function (ConversationParticipant $participant): void {
                $name = User::query()->whereKey($participant->user_id)->value('name');
                if (filled($name)) {
                    $participant->update(['display_name' => $name]);
                }
            });
    }

    /** Keep the job board dominated by mature 4800+ staged cards for marketing captures. */
    private function closeLowNumberOpenBoardClutter(): void
    {
        RepairOrder::query()
            ->where('repair_order_id', '<', 4800)
            ->whereNotIn('status', ['closed', 'closed_paid', 'closed_lost'])
            ->update([
                'status' => RepairOrderStatus::Closed,
                'closed_at' => now(),
                'payment_status' => RepairOrderPaymentStatus::Paid,
                'paid_at' => now(),
            ]);
    }

    private function ensureShopNumberFloor(int $floor): void
    {
        $max = (int) RepairOrder::query()->max('repair_order_id');
        if ($max >= $floor) {
            return;
        }

        $customer = Customer::query()->firstOrCreate(
            ['email' => self::EMAIL_PREFIX.'history@example.test'],
            [
                'first_name' => 'Archive',
                'last_name' => 'Sequence',
                'phone' => '7195550100',
                'customer_type' => 'Retail',
            ],
        );

        $vehicle = Vehicle::query()->firstOrCreate(
            ['vin' => '1HGCM82633A480000'],
            [
                'customer_id' => $customer->id,
                'year' => 2015,
                'make' => 'Honda',
                'model' => 'Civic',
                'plate' => 'SEQ480',
                'plate_state' => 'CO',
                'normalized_vin' => '1HGCM82633A480000',
            ],
        );

        RepairOrder::query()->firstOrCreate(
            ['repair_order_id' => $floor],
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'status' => RepairOrderStatus::Closed,
                'payment_status' => RepairOrderPaymentStatus::Paid,
                'concern_summary' => 'Cloud screenshot sequence floor',
                'opened_at' => now()->subYears(2),
                'closed_at' => now()->subYears(2)->addDays(2),
                'paid_at' => now()->subYears(2)->addDays(2),
            ],
        );
    }

    /**
     * @return array{customer: Customer, vehicle: Vehicle, repairOrder: RepairOrder}
     */
    private function seedHeroRepairOrder(): array
    {
        $advisor = User::query()->where('email', 'advisor@ark.test')->first()
            ?? User::query()->where('email', 'admin@ark.test')->first();
        $tech = User::query()->where('email', 'tech@ark.test')->first();

        $customer = Customer::query()->updateOrCreate(
            ['email' => self::HERO_EMAIL],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Mitchell',
                'phone' => '7195550147',
                'customer_type' => 'Retail',
                'address_line_1' => '1840 Cascade Ave',
                'city' => 'Demo City',
                'state' => 'CO',
                'postal_code' => '80903',
                'notes' => 'Prefers text. Usually replies same morning.',
            ],
        );

        $vehicle = Vehicle::query()->updateOrCreate(
            ['vin' => '5FNRL6H79JB000147'],
            [
                'customer_id' => $customer->id,
                'year' => 2018,
                'make' => 'Honda',
                'model' => 'Odyssey',
                'trim' => 'EX-L',
                'color' => 'White',
                'plate' => 'FAM147',
                'plate_state' => 'CO',
                'engine' => '3.5L V6',
                'transmission' => 'Automatic',
                'drive' => 'FWD',
                'normalized_vin' => '5FNRL6H79JB000147',
            ],
        );

        $repairOrder = RepairOrder::query()->updateOrCreate(
            ['repair_order_id' => self::HERO_SHOP_NUMBER],
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'assigned_technician_id' => $tech?->id,
                'status' => RepairOrderStatus::WaitingApproval,
                'payment_status' => RepairOrderPaymentStatus::Unpaid,
                'concern_summary' => 'Brakes and rough idle',
                'drop_off' => true,
                'opened_at' => now()->subHours(5),
                'mileage_in' => 98640,
            ],
        );

        $repairOrder->lines()->delete();
        $repairOrder->concerns()->delete();

        $brake = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'position' => 1,
            'summary' => 'Front brakes',
            'recommendation_intent' => RecommendationIntent::ImmediateAttention,
            'notes' => 'Customer hears scrape on right turns.',
            'customer_states' => 'Scraping noise when braking from highway speeds.',
            'verified_findings' => 'Front pads at 2mm. Rotors within machine limit. Hardware rusty but reusable.',
            'recommendation' => 'Replace front brake pads and resurface rotors.',
            'disposition' => RepairOrderConcernDisposition::Recommended,
        ]);

        $this->line($repairOrder, $brake->id, [
            'type' => RepairOrderLineType::Labor,
            'description' => 'Replace front brake pads / resurface rotors',
            'quantity' => '1.60',
            'unit_price_cents' => 16500,
        ]);
        $this->line($repairOrder, $brake->id, [
            'type' => RepairOrderLineType::Part,
            'description' => 'Front brake pad set',
            'quantity' => '1.00',
            'unit_price_cents' => 11280,
            'part_cost_cents' => 6240,
            'pricing_mode' => 'matrix',
            'pricing_matrix_key' => 'aft-parts',
            'vendor_name' => "O'Reilly Auto Parts",
            'part_number' => 'SGD1215',
            'procurement_state' => PartProcurementState::None,
        ]);

        $coils = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'position' => 2,
            'summary' => 'Ignition coils',
            'recommendation_intent' => RecommendationIntent::ImmediateAttention,
            'notes' => 'Rough idle after warm-up.',
            'customer_states' => 'Van shudders at stoplights. Check engine light flashing once last week.',
            'verified_findings' => 'Misfire on cylinder 3. Coil pack cracked at boot.',
            'dtcs_summary' => 'P0303',
            'recommendation' => 'Replace ignition coils (set) and plugs while accessible.',
            'disposition' => RepairOrderConcernDisposition::Recommended,
        ]);

        $this->line($repairOrder, $coils->id, [
            'type' => RepairOrderLineType::Labor,
            'description' => 'Replace ignition coils and spark plugs',
            'quantity' => '1.40',
            'unit_price_cents' => 16500,
        ]);
        $this->line($repairOrder, $coils->id, [
            'type' => RepairOrderLineType::Part,
            'description' => 'Ignition coil (set of 6)',
            'quantity' => '1.00',
            'unit_price_cents' => 28900,
            'part_cost_cents' => 16800,
            'pricing_mode' => 'matrix',
            'pricing_matrix_key' => 'aft-parts',
            'vendor_name' => "O'Reilly Auto Parts",
            'part_number' => 'IC707',
            'procurement_state' => PartProcurementState::None,
        ]);
        $this->line($repairOrder, $coils->id, [
            'type' => RepairOrderLineType::Part,
            'description' => 'Spark plug set',
            'quantity' => '1.00',
            'unit_price_cents' => 5400,
            'part_cost_cents' => 2880,
            'pricing_mode' => 'matrix',
            'pricing_matrix_key' => 'aft-parts',
            'vendor_name' => "O'Reilly Auto Parts",
            'part_number' => 'SP16',
        ]);

        $cabin = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'position' => 3,
            'summary' => 'Cabin air filter',
            'recommendation_intent' => RecommendationIntent::Maintenance,
            'notes' => 'Optional this visit.',
            'customer_states' => 'Dusty smell on A/C start.',
            'verified_findings' => 'Cabin filter packed with debris.',
            'recommendation' => 'Replace cabin air filter.',
            'disposition' => RepairOrderConcernDisposition::Deferred,
        ]);

        $this->line($repairOrder, $cabin->id, [
            'type' => RepairOrderLineType::Part,
            'description' => 'Cabin air filter',
            'quantity' => '1.00',
            'unit_price_cents' => 3495,
            'part_cost_cents' => 1800,
            'pricing_mode' => 'matrix',
            'pricing_matrix_key' => 'filters',
        ]);
        $this->line($repairOrder, $cabin->id, [
            'type' => RepairOrderLineType::Labor,
            'description' => 'Replace cabin air filter',
            'quantity' => '0.20',
            'unit_price_cents' => 16500,
        ]);

        $this->totals->recalculateRepairOrder($repairOrder->refresh());

        try {
            // Creator name appears as Advisor on the RO — use Marcus Hale, not ARK Admin.
            $document = $this->documents->createOrRefresh($repairOrder->refresh(), $advisor);
            $this->documents->generatePdf($document);
        } catch (Throwable) {
            // Screenshot staging keeps the estimate snapshot even if local PDF tooling is unavailable.
        }

        return compact('customer', 'vehicle', 'repairOrder');
    }

    /**
     * @param  array{customer: Customer, vehicle: Vehicle, repairOrder: RepairOrder}  $hero
     */
    private function seedHeroConversation(array $hero): void
    {
        $customer = $hero['customer'];
        $repairOrder = $hero['repairOrder'];
        $advisor = User::query()->where('email', 'advisor@ark.test')->first()
            ?? User::query()->where('email', 'admin@ark.test')->first();

        $conversation = $this->conversations->forCustomer($customer);
        $conversation->forceFill([
            'status' => ConversationStatus::Open,
        ])->save();

        $this->linker->linkRepairOrderContext($conversation, $repairOrder);

        // Clear prior staged messages / commitments for idempotent marketing re-runs.
        DB::table('conversation_messages')
            ->where('conversation_id', $conversation->id)
            ->delete();
        OperationalCommitment::query()
            ->where('repair_order_id', $repairOrder->id)
            ->delete();

        $customerParticipant = $this->participants->customer($conversation, $customer);
        $advisorParticipant = $advisor
            ? $this->participants->advisor($conversation, $advisor)
            : $this->participants->system($conversation, 'Shop');

        $thread = [
            [OperationalCommunicationDirection::Inbound, $customerParticipant, 'Can you send the estimate when it\'s ready?', 26 * 60],
            [OperationalCommunicationDirection::Outbound, $advisorParticipant, 'Just sent it — front brakes and ignition coils. Tap the link anytime.', 25 * 60 + 54],
            [OperationalCommunicationDirection::Inbound, $customerParticipant, 'Got it. Looking at it now.', 25 * 60 + 30],
            [OperationalCommunicationDirection::Outbound, $advisorParticipant, 'Any questions before you approve? Happy to walk through it.', 90],
            [OperationalCommunicationDirection::Inbound, $customerParticipant, 'Looks good — go ahead on the coils. Hold the rear pads for now… wait, these are front pads, right?', 48],
            [OperationalCommunicationDirection::Outbound, $advisorParticipant, 'Yes — front pads and rotors. Cabin filter is deferred unless you want it today.', 40],
            [OperationalCommunicationDirection::Inbound, $customerParticipant, 'Front brakes + coils. Skip the cabin filter. Thanks!', 12],
            [OperationalCommunicationDirection::Outbound, $advisorParticipant, 'Perfect — we\'ll get the coils and front brakes moving. I\'ll text when parts land.', 5],
        ];

        foreach ($thread as [$direction, $participant, $body, $minutesAgo]) {
            $this->recorder->record(
                $conversation,
                $participant,
                OperationalCommunicationChannel::Sms,
                $direction,
                $body,
                now()->subMinutes($minutesAgo),
                [
                    'delivery_status' => 'delivered',
                    'actor_user_id' => $direction === OperationalCommunicationDirection::Outbound ? $advisor?->id : null,
                ],
            );
        }
    }

    private function seedBoardCast(): void
    {
        $tech = User::query()->where('email', 'tech@ark.test')->first();

        $cast = [
            [
                'shop' => 4821,
                'email' => self::EMAIL_PREFIX.'james@example.test',
                'first' => 'James', 'last' => 'Walker', 'phone' => '7195550182',
                'vin' => '1FTFW1E59JFA48201', 'year' => 2018, 'make' => 'Ford', 'model' => 'F-150', 'color' => 'Gray',
                'status' => RepairOrderStatus::Estimate, 'concern' => 'Driveline vibration',
                'summary' => 'U-joint inspection',
                'lines' => [
                    ['Labor', 'Road test and driveline inspection', '0.80', 16500],
                    ['Part', 'Rear driveshaft U-joint', '1.00', 7840, 4200],
                ],
            ],
            [
                'shop' => 4829,
                'email' => self::EMAIL_PREFIX.'linda@example.test',
                'first' => 'Linda', 'last' => 'Patel', 'phone' => '7195550194',
                'vin' => '2T3RFREV6KW048290', 'year' => 2019, 'make' => 'Toyota', 'model' => 'RAV4', 'color' => 'Blue',
                'status' => RepairOrderStatus::WaitingApproval, 'concern' => 'Maintenance before Denver trip',
                'summary' => 'Oil service and front brakes',
                'lines' => [
                    ['Labor', 'Oil and filter service', '0.50', 16500],
                    ['Part', 'Synthetic oil and filter kit', '1.00', 6895, 3750],
                    ['Labor', 'Replace front brake pads', '1.10', 16500],
                    ['Part', 'Front brake pad set', '1.00', 9800, 6100],
                ],
            ],
            [
                'shop' => 4834,
                'email' => self::EMAIL_PREFIX.'robert@example.test',
                'first' => 'Robert', 'last' => 'Kim', 'phone' => '7195550166',
                'vin' => '5YJ3E1EA7KF048340', 'year' => 2020, 'make' => 'Tesla', 'model' => 'Model 3', 'color' => 'Black',
                'status' => RepairOrderStatus::WaitingParts, 'concern' => 'Alignment after tire wear',
                'summary' => 'Four-wheel alignment',
                'tech' => true,
                'lines' => [
                    ['Labor', 'Four-wheel alignment', '1.00', 12900],
                ],
            ],
            [
                'shop' => 4841,
                'email' => self::EMAIL_PREFIX.'maria@example.test',
                'first' => 'Maria', 'last' => 'Santos', 'phone' => '7195550133',
                'vin' => '1G1ZD5ST8JF048410', 'year' => 2017, 'make' => 'Chevrolet', 'model' => 'Malibu', 'color' => 'Silver',
                'status' => RepairOrderStatus::InProgress, 'concern' => 'A/C weak in heat',
                'summary' => 'A/C performance check',
                'tech' => true,
                'lines' => [
                    ['Labor', 'A/C performance diagnosis', '1.00', 16500],
                    ['Part', 'A/C compressor clutch relay', '1.00', 4200, 2100],
                ],
            ],
            [
                'shop' => 4844,
                'email' => self::EMAIL_PREFIX.'daniel@example.test',
                'first' => 'Daniel', 'last' => 'Brooks', 'phone' => '7195550171',
                'vin' => '3VW2B7AJ5JM048440', 'year' => 2016, 'make' => 'Volkswagen', 'model' => 'Jetta', 'color' => 'Red',
                'status' => RepairOrderStatus::Draft, 'concern' => 'Check engine light',
                'summary' => 'Needs diagnosis',
                'lines' => [
                    ['Labor', 'Diagnostic scan and road test', '1.00', 16500],
                ],
            ],
            [
                'shop' => 4846,
                'email' => self::EMAIL_PREFIX.'priya@example.test',
                'first' => 'Priya', 'last' => 'Nguyen', 'phone' => '7195550155',
                'vin' => 'KM8J33A46KU048460', 'year' => 2022, 'make' => 'Hyundai', 'model' => 'Tucson', 'color' => 'Green',
                'status' => RepairOrderStatus::Approved, 'concern' => 'Timing chain rattle on cold start',
                'summary' => 'Approved — waiting bay',
                'tech' => true,
                'lines' => [
                    ['Labor', 'Replace timing chain kit', '8.50', 16500],
                    ['Part', 'Timing chain kit', '1.00', 62000, 38500],
                ],
            ],
        ];

        foreach ($cast as $row) {
            $customer = Customer::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'first_name' => $row['first'],
                    'last_name' => $row['last'],
                    'phone' => $row['phone'],
                    'customer_type' => 'Retail',
                    'city' => 'Demo City',
                    'state' => 'CO',
                ],
            );

            $vehicle = Vehicle::query()->updateOrCreate(
                ['vin' => $row['vin']],
                [
                    'customer_id' => $customer->id,
                    'year' => $row['year'],
                    'make' => $row['make'],
                    'model' => $row['model'],
                    'color' => $row['color'],
                    'plate_state' => 'CO',
                    'normalized_vin' => $row['vin'],
                ],
            );

            $repairOrder = RepairOrder::query()->updateOrCreate(
                ['repair_order_id' => $row['shop']],
                [
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'assigned_technician_id' => ! empty($row['tech']) ? $tech?->id : null,
                    'status' => $row['status'],
                    'payment_status' => RepairOrderPaymentStatus::Unpaid,
                    'concern_summary' => $row['concern'],
                    'drop_off' => true,
                    'opened_at' => now()->subHours(random_int(2, 48)),
                ],
            );

            $repairOrder->lines()->delete();
            $repairOrder->concerns()->delete();

            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'position' => 1,
                'summary' => $row['summary'],
                'recommendation_intent' => RecommendationIntent::PlanSoon,
                'disposition' => in_array($row['status'], [RepairOrderStatus::Approved, RepairOrderStatus::InProgress, RepairOrderStatus::WaitingParts], true)
                    ? RepairOrderConcernDisposition::Approved
                    : RepairOrderConcernDisposition::Recommended,
            ]);

            foreach ($row['lines'] as $line) {
                $this->line($repairOrder, $concern->id, [
                    'type' => $line[0] === 'Part' ? RepairOrderLineType::Part : RepairOrderLineType::Labor,
                    'description' => $line[1],
                    'quantity' => $line[2],
                    'unit_price_cents' => $line[3],
                    'part_cost_cents' => $line[4] ?? null,
                    'pricing_mode' => isset($line[4]) ? 'matrix' : null,
                    'pricing_matrix_key' => isset($line[4]) ? 'aft-parts' : null,
                ]);
            }

            $this->totals->recalculateRepairOrder($repairOrder->refresh());
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function line(RepairOrder $repairOrder, int $concernId, array $line): void
    {
        $settings = ShopSettings::current();
        $matrixKey = $line['pricing_matrix_key'] ?? null;
        $matrix = $matrixKey ? $settings->partsMatrixByKey($matrixKey) : null;
        $suggested = isset($line['part_cost_cents'], $line['pricing_matrix_key'])
            ? $this->totals->matrixSuggestedPriceCents($line['part_cost_cents'], $settings, $line['pricing_matrix_key'])
            : null;

        $repairOrder->lines()->create([
            'repair_order_concern_id' => $concernId,
            'type' => $line['type']->value,
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price_cents' => $line['unit_price_cents'],
            'part_cost_cents' => $line['part_cost_cents'] ?? null,
            'matrix_suggested_price_cents' => $suggested,
            'pricing_mode' => $line['pricing_mode'] ?? null,
            'pricing_matrix_key' => $matrixKey,
            'pricing_matrix_name' => $matrix['name'] ?? null,
            'matrix_applied' => $suggested !== null && $suggested === $line['unit_price_cents'],
            'vendor_name' => $line['vendor_name'] ?? null,
            'part_number' => $line['part_number'] ?? null,
            'procurement_state' => ($line['procurement_state'] ?? PartProcurementState::None)->value,
            'subtotal_cents' => $this->totals->lineTotalCents($line['quantity'], $line['unit_price_cents']),
        ]);
    }
}
