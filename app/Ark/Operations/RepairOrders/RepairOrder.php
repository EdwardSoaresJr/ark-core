<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalRevocationEvent;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunication;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerIdentityPressure;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Encounters\Encounter;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionTemplate;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleIdentityPressure;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['public_id', 'growth_session_id', 'repair_order_id', 'encounter_id', 'customer_id', 'vehicle_id', 'assigned_technician_id', 'required_inspection_template_id', 'status', 'close_variant_key', 'lost_reason_key', 'lost_reason_note', 'lost_reason_recorded_at', 'lost_reason_recorded_by', 'review_request_sent', 'review_not_requested_reason', 'review_request_recorded_at', 'review_request_recorded_by', 'estimate_version', 'estimate_version_actor_id', 'estimate_version_at', 'payment_status', 'collection_disposition', 'collection_disposition_reason', 'paid_at', 'concern_summary', 'visit_reason', 'tow_incoming', 'waiting_here', 'drop_off', 'needs_shuttle', 'warranty', 'fleet', 'appointment', 'mileage_in', 'mileage_out', 'opened_at', 'closed_at', 'posted_at'])]
class RepairOrder extends Model
{
    protected static function booted(): void
    {
        // Shop-facing number is the route key. Never persist (or leave) null -
        // a null repair_order_id breaks route() on index and every show link.
        static::saving(function (RepairOrder $repairOrder): void {
            if ($repairOrder->public_id === null || $repairOrder->public_id === '') {
                $repairOrder->public_id = (string) Str::uuid();
            }

            if ($repairOrder->repair_order_id !== null) {
                return;
            }

            $repairOrder->repair_order_id = static::nextShopRepairOrderId();
        });
    }

    public function ensurePublicId(): string
    {
        if (filled($this->public_id)) {
            return (string) $this->public_id;
        }

        $this->forceFill(['public_id' => (string) Str::uuid()])->save();

        return (string) $this->public_id;
    }

    /** Next shop-facing RO number; continues legacy/import sequence and is not tied to the PK. */
    public static function nextShopRepairOrderId(): int
    {
        $currentMax = (int) static::query()->max('repair_order_id');

        return max($currentMax + 1, 1);
    }

    public function getRouteKeyName(): string
    {
        return 'repair_order_id';
    }

    protected function casts(): array
    {
        return [
            'repair_order_id' => 'integer',
            'estimate_version' => 'integer',
            'estimate_version_at' => 'datetime',
            'status' => RepairOrderStatusCast::class,
            'payment_status' => RepairOrderPaymentStatus::class,
            'collection_disposition' => RepairOrderCollectionDisposition::class,
            'paid_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'posted_at' => 'datetime',
            'lost_reason_key' => RepairOrderLostReason::class,
            'lost_reason_recorded_at' => 'datetime',
            'review_request_sent' => 'boolean',
            'review_request_recorded_at' => 'datetime',
            'tow_incoming' => 'boolean',
            'waiting_here' => 'boolean',
            'drop_off' => 'boolean',
            'needs_shuttle' => 'boolean',
            'warranty' => 'boolean',
            'fleet' => 'boolean',
            'appointment' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function growthSession(): BelongsTo
    {
        return $this->belongsTo(\App\Ark\Growth\Models\GrowthSession::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Transitional compatibility only - do not use for technician work ownership.
     * Prefer Repair Action owners (`RepairOrderWorkGroup.owner_user_id`).
     * Kept for seed/defaulting until column retirement.
     */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function requiredInspectionTemplate(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'required_inspection_template_id');
    }

    public function estimateVersionActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estimate_version_actor_id');
    }

    public function lostReasonRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lost_reason_recorded_by');
    }

    public function reviewRequestRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_request_recorded_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
    }

    public function worksheetSessions(): HasMany
    {
        return $this->hasMany(RepairOrderWorksheetSession::class);
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(RepairOrderConcern::class)->orderBy('position')->orderBy('id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepairOrderLine::class)
            ->orderByRaw(RepairOrderLineWorksheetOrder::sqlCaseExpression())
            ->orderBy('id');
    }

    public function estimateDocuments(): HasMany
    {
        return $this->hasMany(EstimateDocument::class)->latest('id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(RepairOrderLedgerEntry::class)->latest('recorded_at')->latest('id');
    }

    public function approvalEvents(): HasMany
    {
        return $this->hasMany(ApprovalEvent::class, 'visit_id')->latest('approved_at')->latest('id');
    }

    public function approvalRevocationEvents(): HasMany
    {
        return $this->hasMany(ApprovalRevocationEvent::class, 'visit_id')->latest('revoked_at')->latest('id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(OperationalCommunication::class)->latest('occurred_at')->latest('id');
    }

    public function communicationEvents(): HasMany
    {
        return $this->hasMany(CommunicationEvent::class)->latest('occurred_at')->latest('id');
    }

    public function operationalCommitments(): HasMany
    {
        return $this->hasMany(OperationalCommitment::class)->latest('due_at')->latest('id');
    }

    public function estimateAccessTokens(): HasMany
    {
        return $this->hasMany(EstimateAccessToken::class);
    }

    public function inspection(): HasOne
    {
        return $this->hasOne(Inspection::class);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canChangeVehicle(): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if ($this->relationLoaded('concerns') && $this->relationLoaded('lines')) {
            return $this->concerns->isEmpty() && $this->lines->isEmpty();
        }

        return ! $this->concerns()->exists() && ! $this->lines()->exists();
    }

    public function statusDisplayLabel(): string
    {
        return app(RepairOrderStatusCatalog::class)
            ->displayLabel($this->status, $this->close_variant_key);
    }

    public function closeCountsInSalesMetrics(): bool
    {
        if (! $this->status->is(RepairOrderStatus::Closed)) {
            return false;
        }

        return app(RepairOrderStatusCatalog::class)
            ->closeVariantAffectsMetrics($this->close_variant_key);
    }

    /** Living estimate PDFs refresh on every change until the RO is closed. */
    public function estimateDocumentIsFrozen(): bool
    {
        return $this->isTerminal();
    }

    public function allowsTechnicianClear(): bool
    {
        return ! $this->status->is(RepairOrderStatus::InProgress)
            && ! $this->status->is(RepairOrderStatus::WaitingParts);
    }

    /** Shop-facing repair order number shown in URLs, PartsTech, PDFs, and RO labels. */
    public function repairOrderId(): string
    {
        return (string) $this->repair_order_id;
    }

    public function serviceAdvisorName(): ?string
    {
        $this->loadMissing(['estimateDocuments.creator']);

        $estimateDocument = $this->estimateDocuments
            ->first(fn (EstimateDocument $document): bool => $document->document_type === FinancialDocumentType::Estimate);

        if ($estimateDocument !== null) {
            $fromSnapshot = data_get($estimateDocument->snapshot_json, 'generated_by.name');

            if (filled($fromSnapshot)) {
                return (string) $fromSnapshot;
            }

            if (filled($estimateDocument->creator?->name)) {
                return $estimateDocument->creator->name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function intakeQuickFlags(): array
    {
        return collect([
            $this->waiting_here ? 'Waiting Here' : null,
            $this->drop_off ? 'Drop Off' : null,
            $this->needs_shuttle ? 'Needs Shuttle' : null,
            $this->fleet ? 'Fleet' : null,
            $this->warranty ? 'Warranty' : null,
            $this->tow_incoming ? 'Tow-In' : null,
            $this->appointment ? 'Appointment' : null,
        ])->filter()->values()->all();
    }

    public function displayOpenedAt(): Carbon
    {
        return $this->opened_at ?? $this->created_at;
    }

    public function displayClosedAt(): ?Carbon
    {
        if ($this->status->isTerminal() || $this->status->is(RepairOrderStatus::ReadyPickup)) {
            return $this->closed_at;
        }

        return null;
    }

    public function paymentStatus(): RepairOrderPaymentStatus
    {
        return $this->payment_status ?? RepairOrderPaymentStatus::Unpaid;
    }

    public function isPaid(): bool
    {
        return app(BalanceDueCalculator::class)->forRepairOrder($this)->isPaid();
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function readyToPost(): bool
    {
        if ($this->posted_at !== null || $this->close_variant_key === 'lost') {
            return false;
        }

        $balance = $this->balanceDue();

        return $balance->hasIssuedInvoice && $balance->isPaid();
    }

    public function balanceDue(): BalanceDueResult
    {
        return app(BalanceDueCalculator::class)->forRepairOrder($this);
    }

    public function pickupHandoffLabel(): string
    {
        if ($this->status->is(RepairOrderStatus::ReadyPickup)) {
            return $this->isPaid()
                ? RepairOrderPaymentStatus::Paid->handoffLabel()
                : RepairOrderPaymentStatus::Unpaid->handoffLabel();
        }

        if ($this->isTerminal()) {
            return 'Delivered / archived';
        }

        return $this->status->label();
    }

    /**
     * Who is doing the work. Split-job owners when those exist; otherwise the assigned technician.
     */
    public function technicianOwnershipLabel(): string
    {
        $this->loadMissing('assignedTechnician');

        $assigned = trim((string) ($this->assignedTechnician?->name ?? ''));

        return $this->repairActionOwnerSummary()
            ?? ($assigned !== '' ? $assigned : null)
            ?? 'Needs owner';
    }

    public function repairActionOwnerSummary(): ?string
    {
        $this->loadMissing('concerns.workGroups.ownerUser');

        $names = $this->concerns
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->workGroups)
            ->filter(fn (RepairOrderWorkGroup $group): bool => $group->hasOwner())
            ->map(fn (RepairOrderWorkGroup $group): ?string => $group->ownerUser?->name)
            ->filter(fn (?string $name): bool => filled($name))
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() === 1) {
            return (string) $names->first();
        }

        $shown = $names->take(2)->implode(', ');

        return $names->count() > 2
            ? $shown.' +'.($names->count() - 2)
            : $shown;
    }

    public function hasRepairActionOwner(): bool
    {
        $this->loadMissing('concerns.workGroups');

        return $this->concerns
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->workGroups)
            ->contains(fn (RepairOrderWorkGroup $group): bool => $group->hasOwner());
    }

    public function resolvedMileageIn(): ?int
    {
        if ($this->mileage_in !== null) {
            return (int) $this->mileage_in;
        }

        return $this->vehicle?->legacyOdometerReading();
    }

    public function resolvedMileageOut(): ?int
    {
        return $this->mileage_out !== null ? (int) $this->mileage_out : null;
    }

    public function executionPostureLabel(): string
    {
        if ($this->hasUnresolvedApprovedParts() && ! $this->status->is(RepairOrderStatus::InProgress)) {
            return 'Blocked by parts';
        }

        return match ($this->status->enum()) {
            RepairOrderStatus::Draft => 'Diagnostic intake',
            RepairOrderStatus::Estimate => 'Diagnosis / estimate',
            RepairOrderStatus::WaitingApproval => 'Waiting customer approval',
            RepairOrderStatus::Approved => 'Approved / needs dispatch',
            RepairOrderStatus::WaitingParts => 'Waiting Parts',
            RepairOrderStatus::ReadyForWork => 'Ready for execution',
            RepairOrderStatus::InProgress => 'In Progress',
            RepairOrderStatus::QualityCheck => 'Quality check',
            RepairOrderStatus::Completed => 'Work complete',
            RepairOrderStatus::Invoiced => 'Invoiced / pickup',
            RepairOrderStatus::ReadyPickup => 'Work complete / pickup',
            RepairOrderStatus::Closed => 'Closed',
            default => $this->status->label(),
        };
    }

    public function executionNextAction(): string
    {
        if ($this->hasUnresolvedApprovedParts() && ! $this->status->is(RepairOrderStatus::InProgress)) {
            return $this->procurementNextActionSummary() ?: 'Clear parts blocker';
        }

        return match ($this->status->enum()) {
            RepairOrderStatus::Draft => 'Diagnose concern',
            RepairOrderStatus::Estimate => 'Finish estimate',
            RepairOrderStatus::WaitingApproval => 'Wait for advisor authorization follow-up',
            RepairOrderStatus::Approved => $this->hasRepairActionOwner() ? 'Dispatch owned Repair Actions' : 'Assign Repair Action owners',
            RepairOrderStatus::WaitingParts => 'Clear parts blocker',
            RepairOrderStatus::ReadyForWork => $this->hasRepairActionOwner() ? 'Start work' : 'Assign Repair Action owners',
            RepairOrderStatus::InProgress => 'Continue approved work',
            RepairOrderStatus::QualityCheck => 'Complete quality check',
            RepairOrderStatus::Completed => 'Issue invoice',
            RepairOrderStatus::Invoiced => $this->isPaid() ? 'Close paid repair order' : 'Collect payment before release',
            RepairOrderStatus::ReadyPickup => 'Ready for advisor closeout',
            RepairOrderStatus::Closed => 'Archived',
            default => 'Review workflow',
        };
    }

    public function communicationPostureLabel(): string
    {
        $lastCommunication = $this->latestCommunicationEvent();

        if ($lastCommunication === null) {
            return 'No customer communication logged';
        }

        return $lastCommunication->event_type->label().' · '.$lastCommunication->channel->label();
    }

    public function communicationNextAction(): string
    {
        $lastCommunication = $this->latestCommunicationEvent();

        if ($this->status->is(RepairOrderStatus::WaitingApproval)) {
            if ($lastCommunication === null) {
                return 'Send estimate or call for authorization';
            }

            return match ($lastCommunication->event_type) {
                OperationalCommunicationType::EstimateViewed => 'Follow up viewed estimate',
                OperationalCommunicationType::EstimateSent => 'Wait for customer response',
                OperationalCommunicationType::ApprovalFollowUp => 'Waiting customer response',
                OperationalCommunicationType::CustomerReply => 'Review customer reply',
                OperationalCommunicationType::CustomerUnreachable => 'Try alternate contact',
                default => 'Follow up approval',
            };
        }

        if ($this->status->is(RepairOrderStatus::ReadyPickup)) {
            return $lastCommunication?->event_type === OperationalCommunicationType::PickupNotified
                ? 'Await customer arrival'
                : 'Notify customer pickup ready';
        }

        if ($lastCommunication?->event_type === OperationalCommunicationType::CustomerReply) {
            return 'Review customer reply';
        }

        return 'No communication action pending';
    }

    public function latestCommunicationEvent(): ?CommunicationEvent
    {
        if ($this->relationLoaded('communicationEvents')) {
            return $this->communicationEvents->first();
        }

        return $this->communicationEvents()->first();
    }

    public function latestOperationalCommunication(): ?OperationalCommunication
    {
        if ($this->relationLoaded('communications')) {
            return $this->communications->first();
        }

        return $this->communications()->first();
    }

    public function approvedLaborLineCount(): int
    {
        return $this->approvedLines()
            ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Labor)
            ->count();
    }

    public function futureWorkCount(): int
    {
        return $this->futureWorkConcerns()->count();
    }

    public function futureWorkSubtotalCents(): int
    {
        $concernIds = $this->futureWorkConcerns()->pluck('id');

        $this->loadMissing('lines');

        return (int) $this->lines
            ->whereIn('repair_order_concern_id', $concernIds)
            ->sum('subtotal_cents');
    }

    public function futureWorkSummary(): string
    {
        $concerns = $this->futureWorkConcerns();

        if ($concerns->isEmpty()) {
            return 'No deferred work retained';
        }

        $counts = $concerns->countBy(fn (RepairOrderConcern $concern): string => $concern->disposition->label());

        return $counts
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->values()
            ->join(' · ');
    }

    public function futureWorkNextAction(): string
    {
        $concerns = $this->futureWorkConcerns();

        if ($concerns->isEmpty()) {
            return 'No future-work action pending';
        }

        $intent = RecommendationIntent::strongestDeferredFollowUp($concerns);

        if ($intent !== null) {
            return $intent->deferredFollowUpAction();
        }

        return 'Keep deferred work visible in vehicle history';
    }

    /**
     * @return Collection<int, RepairOrderConcern>
     */
    public function futureWorkConcerns(): Collection
    {
        $this->loadMissing('concerns.lines');

        return $this->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Deferred)
            ->values();
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    public function approvedLines(): Collection
    {
        if ($this->relationLoaded('lines')) {
            $this->lines->loadMissing('concern');

            return $this->lines
                ->filter(fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Approved)
                ->values();
        }

        $this->loadMissing('concerns.lines');

        return $this->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->flatMap(fn (RepairOrderConcern $concern): Collection => $concern->lines)
            ->values();
    }

    public function ensureOpenForEditing(): void
    {
        abort_if($this->isTerminal(), 423, 'Terminal repair orders cannot be changed.');
    }

    public function hasUnresolvedApprovedParts(): bool
    {
        return $this->approvedPartLines()
            ->contains(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement());
    }

    public function customerIdentityPressure(): CustomerIdentityPressure
    {
        $this->loadMissing('customer');

        return $this->customer?->identityPressure() ?? CustomerIdentityPressure::Critical;
    }

    public function customerIdentityPressureLabel(): string
    {
        return $this->customerIdentityPressure()->label();
    }

    public function customerIdentityPressureHint(): ?string
    {
        return $this->customer?->identityPressureHint();
    }

    public function vehicleIdentityPressure(): VehicleIdentityPressure
    {
        $this->loadMissing('vehicle');

        return $this->vehicle?->identityPressure() ?? VehicleIdentityPressure::NoVin;
    }

    public function vehicleIdentityPressureLabel(): string
    {
        return $this->vehicleIdentityPressure()->label();
    }

    public function vehicleIdentityPressureHint(): ?string
    {
        return $this->vehicle?->identityPressureHint();
    }

    public function missingVehicleVin(): bool
    {
        $this->loadMissing('vehicle');

        return ! ($this->vehicle?->hasVin() ?? false);
    }

    public const ESTIMATE_SEND_DRAFT_ONLY_MESSAGE = 'This estimate only contains Draft concerns. Draft work is not shown to the customer. Mark the work you are recommending before sending it.';

    public function outgoingEstimateIsDraftOnly(): bool
    {
        $dispositions = $this->concerns()->pluck('disposition');

        if ($dispositions->isEmpty()) {
            return false;
        }

        return $dispositions->every(function (mixed $disposition): bool {
            if ($disposition instanceof RepairOrderConcernDisposition) {
                return $disposition === RepairOrderConcernDisposition::Draft;
            }

            return $disposition === RepairOrderConcernDisposition::Draft->value;
        });
    }

    public function estimateConcernReviewUrl(): string
    {
        return route('operations.repair-orders.show', $this).'#estimate-lines';
    }

    public function ensureEstimateSendAllowed(bool $acknowledgeMissingVin = false): void
    {
        if ($this->outgoingEstimateIsDraftOnly()) {
            throw new \RuntimeException(self::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE);
        }

        if ($this->missingVehicleVin() && ! $acknowledgeMissingVin) {
            throw new \RuntimeException(VehicleIdentityPressure::NoVin->estimateSendBlockedMessage());
        }
    }

    public function partsPressure(): PartsPressure
    {
        $lines = $this->approvedPartLines();

        if ($lines->isEmpty()) {
            return PartsPressure::NoPartsNeeded;
        }

        $unresolved = $lines->filter(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement());
        $resolved = $lines->reject(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement());

        if ($unresolved->isEmpty()) {
            return PartsPressure::AllPartsAvailable;
        }

        if ($resolved->isNotEmpty()) {
            return PartsPressure::PartialParts;
        }

        if ($unresolved->contains(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Backordered)) {
            return PartsPressure::Backordered;
        }

        return PartsPressure::WaitingParts;
    }

    public function partsPressureLabel(): string
    {
        return $this->partsPressure()->label();
    }

    public function partsPressureSummary(?PartsPressure $pressure = null): ?string
    {
        $pressure ??= $this->partsPressure();

        if (! $pressure->showsChip()) {
            return null;
        }

        $unresolved = $this->approvedPartLines()
            ->filter(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement());

        if ($unresolved->isEmpty()) {
            return null;
        }

        $visible = $unresolved->take(2)->map(function (RepairOrderLine $line): string {
            $description = trim($line->description);
            $stateLabel = $line->procurementPressureLabel();

            return $description !== ''
                ? "{$description} {$stateLabel}"
                : "1 part {$stateLabel}";
        });

        $summary = $visible->join(' · ');

        $remaining = $unresolved->count() - $visible->count();

        if ($remaining > 0) {
            $summary .= ' · +'.$remaining.' more';
        }

        return $summary !== '' ? $summary : $this->partsBlockerSummary();
    }

    public function workboardLaneStatus(): RepairOrderWorkflowStatus
    {
        return RepairOrderWorkflowStatus::from($this->status);
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    public function approvedPartLines(): Collection
    {
        if ($this->relationLoaded('lines')) {
            $this->lines->loadMissing('concern');

            return $this->lines
                ->filter(fn (RepairOrderLine $line): bool => $line->isPart())
                ->filter(fn (RepairOrderLine $line): bool => $line->concern?->disposition === RepairOrderConcernDisposition::Approved)
                ->values();
        }

        $this->loadMissing('concerns.lines');

        return $this->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->flatMap(fn (RepairOrderConcern $concern): Collection => $concern->lines)
            ->filter(fn (RepairOrderLine $line): bool => $line->isPart())
            ->values();
    }

    public function partsBlockerSummary(): ?string
    {
        $counts = $this->approvedPartLines()
            ->filter(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement())
            ->countBy(fn (RepairOrderLine $line): string => $line->procurementPressureLabel());

        if ($counts->isEmpty()) {
            return null;
        }

        return $counts
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->values()
            ->join(' · ');
    }

    /**
     * @return array<string, int>
     */
    public function approvedPartReadinessCounts(): array
    {
        $lines = $this->approvedPartLines();

        return [
            'needs_ordered' => $lines->filter(fn (RepairOrderLine $line): bool => $line->part_source !== PartLineSource::CustomerSupplied && $line->procurementState() === PartProcurementState::None)->count(),
            'awaiting_customer' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::AwaitingCustomer)->count(),
            'confirm_customer_part' => $lines->filter(fn (RepairOrderLine $line): bool => $line->part_source === PartLineSource::CustomerSupplied && $line->procurementState()->isShopProcurementState())->count(),
            'sourcing' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Sourcing)->count(),
            'ordered' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Ordered)->count(),
            'partial' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Partial)->count(),
            'backordered' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Backordered)->count(),
            'received' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Received)->count(),
            'installed' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Installed)->count(),
            'canceled' => $lines->filter(fn (RepairOrderLine $line): bool => $line->procurementState() === PartProcurementState::Canceled)->count(),
        ];
    }

    public function procurementReadinessSummary(): string
    {
        $counts = $this->approvedPartReadinessCounts();

        $summary = collect([
            'Need ordered' => $counts['needs_ordered'],
            'Waiting on customer' => $counts['awaiting_customer'],
            'Confirm customer part' => $counts['confirm_customer_part'],
            'Sourcing' => $counts['sourcing'],
            'Ordered' => $counts['ordered'],
            'Partial' => $counts['partial'],
            'Backordered' => $counts['backordered'],
            'Received' => $counts['received'],
            'Installed' => $counts['installed'],
            'Canceled' => $counts['canceled'],
        ])->filter(fn (int $count): bool => $count > 0);

        if ($summary->isEmpty()) {
            return 'No approved parts blocking execution';
        }

        return $summary
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->values()
            ->join(' · ');
    }

    public function procurementNextActionSummary(): ?string
    {
        $summary = $this->approvedPartLines()
            ->filter(fn (RepairOrderLine $line): bool => $line->hasUnresolvedProcurement())
            ->countBy(fn (RepairOrderLine $line): string => $line->procurementNextAction());

        if ($summary->isEmpty()) {
            return null;
        }

        return $summary
            ->map(fn (int $count, string $action): string => $count.' '.$action)
            ->values()
            ->join(' · ');
    }
}
