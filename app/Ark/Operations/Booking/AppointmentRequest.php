<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What a customer asked for online. A pending request is not an appointment: it reserves no
 * capacity until staff schedule it (request mode). In instant mode the appointment is created
 * with it and the request records how it was booked.
 *
 * @property list<array{year: int|null, make: string|null, model: string|null, vin: string|null}>|null $vehicles
 * @property list<array{question_id: int, label: string, answer: string}>|null $answers
 * @property array{line1?: string, line2?: string, city?: string, state?: string, postal_code?: string, notes?: string}|null $service_address
 */
#[Fillable([
    'uuid',
    'submission_key',
    'status',
    'mode',
    'lead_id',
    'appointment_id',
    'booking_service_id',
    'service_name',
    'service_option',
    'customer_id',
    'vehicle_id',
    'contact_name',
    'contact_phone',
    'contact_email',
    'preferred_date',
    'preferred_period',
    'requested_starts_at',
    'requested_ends_at',
    'duration_minutes',
    'workload_hours',
    'vehicle_count',
    'vehicles',
    'concern',
    'answers',
    'location_mode',
    'service_address',
    'context',
    'decided_by_user_id',
    'decided_at',
    'decline_reason',
])]
class AppointmentRequest extends Model
{
    protected static function booted(): void
    {
        static::creating(function (AppointmentRequest $request): void {
            if (! filled($request->uuid)) {
                $request->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => AppointmentRequestStatus::class,
            'mode' => BookingMode::class,
            'preferred_date' => 'date',
            'requested_starts_at' => 'datetime',
            'requested_ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'workload_hours' => 'decimal:2',
            'vehicle_count' => 'integer',
            'vehicles' => 'array',
            'answers' => 'array',
            'location_mode' => ServiceLocationType::class,
            'service_address' => 'array',
            'context' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookingService::class, 'booking_service_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === AppointmentRequestStatus::Pending;
    }

    public function serviceLabel(): string
    {
        $name = trim((string) $this->service_name);
        $option = trim((string) $this->service_option);

        if ($name === '') {
            return 'Appointment request';
        }

        return $option !== '' ? "{$name} · {$option}" : $name;
    }

    public function preferenceLabel(): string
    {
        if ($this->preferred_date === null) {
            return 'No preference';
        }

        $date = $this->preferred_date->format('D, M j');
        $period = (string) $this->preferred_period;

        $label = $period === '' || $period === AppointmentRequestAvailability::PERIOD_ANY
            ? $date.' · Flexible'
            : $date.' · '.AppointmentRequestAvailability::periodLabel($period);

        $asked = $this->askedTimeLabel();

        return $asked !== null ? $label.', around '.$asked : $label;
    }

    /**
     * The exact time the customer picked when online booking could not confirm it and sent a request instead.
     */
    public function askedTimeLabel(): ?string
    {
        $time = (string) ($this->context['asked_time'] ?? '');

        return preg_match('/^\d{2}:\d{2}$/', $time) === 1 ? Carbon::createFromFormat('H:i', $time)->format('g:i A') : null;
    }

    /**
     * Required availability checks that could not run when the customer booked ("Travel time").
     *
     * @return list<string>
     */
    public function uncheckedRules(): array
    {
        $labels = $this->context['unchecked'] ?? [];

        return is_array($labels) ? array_values(array_filter($labels, fn (mixed $label): bool => is_string($label) && $label !== '')) : [];
    }

    public function locationType(): ServiceLocationType
    {
        return $this->location_mode instanceof ServiceLocationType ? $this->location_mode : ServiceLocationType::Shop;
    }

    public function addressLabel(): ?string
    {
        $address = is_array($this->service_address) ? $this->service_address : [];
        $parts = array_filter([
            trim((string) ($address['line1'] ?? '')),
            trim((string) ($address['line2'] ?? '')),
            trim((string) ($address['city'] ?? '')),
            trim(trim((string) ($address['state'] ?? '')).' '.trim((string) ($address['postal_code'] ?? ''))),
        ], fn (string $part): bool => $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return list<string>
     */
    public function vehicleLabels(): array
    {
        $labels = [];
        foreach (is_array($this->vehicles) ? $this->vehicles : [] as $vehicle) {
            $label = trim(implode(' ', array_filter([
                $vehicle['year'] ?? null,
                $vehicle['make'] ?? null,
                $vehicle['model'] ?? null,
            ])));
            if (filled($vehicle['vin'] ?? null)) {
                $label .= ($label === '' ? '' : ' · ').'VIN '.$vehicle['vin'];
            }
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /** A question as a label before its answer: "Under warranty? Yes", "Parking: Driveway". */
    public static function answerLabel(string $question): string
    {
        $question = trim($question);

        return str_ends_with($question, '?') ? $question : $question.':';
    }

    /**
     * Notes staff see on the appointment, assembled from what the customer entered.
     */
    public function staffNotes(): string
    {
        $lines = ['Booked online: '.$this->serviceLabel()];

        if ($this->mode === BookingMode::Request) {
            $lines[] = 'Customer preferred: '.$this->preferenceLabel();
        }

        if (($unchecked = $this->uncheckedRules()) !== []) {
            $lines[] = 'Not checked online: '.implode(', ', $unchecked).'. Confirm before scheduling.';
        }

        $vehicles = $this->vehicleLabels();
        if (count($vehicles) > 1) {
            $lines[] = 'Vehicles ('.count($vehicles).'): '.implode('; ', $vehicles);
        }

        if ($this->locationType()->isOnSite()) {
            $lines[] = $this->locationType()->label().': '.($this->addressLabel() ?? 'address not given');
            $notes = trim((string) ($this->service_address['notes'] ?? ''));
            if ($notes !== '') {
                $lines[] = 'Access notes: '.$notes;
            }
        }

        foreach (is_array($this->answers) ? $this->answers : [] as $answer) {
            $lines[] = self::answerLabel((string) ($answer['label'] ?? '')).' '.trim((string) ($answer['answer'] ?? ''));
        }

        return mb_substr(implode("\n", $lines), 0, 2000);
    }
}
