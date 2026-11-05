<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationLifecycle;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Collection;

/**
 * What an online booking may know about the person who verified a contact.
 *
 * Callers pass only contacts proven by a verification code. A customer is
 * recognized when exactly one record in this shop carries a verified contact;
 * no match, or more than one, discloses nothing. Vehicles and open
 * recommendations are reachable only through the recognized customer, and
 * carry customer-facing fields only: no prices, advisor notes or VINs.
 */
final class BookingRecognition
{
    public const RECOGNIZED = 'recognized';

    public const UNKNOWN = 'unknown';

    public const AMBIGUOUS = 'ambiguous';

    private function __construct(
        public readonly string $status,
        private readonly ?Customer $customer,
    ) {}

    public static function forVerifiedContact(?string $verifiedPhone, ?string $verifiedEmail): self
    {
        $ids = collect();
        $phone = PhoneNumber::normalize((string) $verifiedPhone);

        if ($phone !== null && strlen($phone) === 10) {
            $ids = $ids->merge(Customer::query()->where('phone', $phone)->limit(2)->pluck('id'));
        }

        $email = strtolower(trim((string) $verifiedEmail));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $ids = $ids->merge(Customer::query()->whereRaw('LOWER(email) = ?', [$email])->limit(2)->pluck('id'));
        }

        $ids = $ids->unique()->values();

        return match ($ids->count()) {
            0 => new self(self::UNKNOWN, null),
            1 => new self(self::RECOGNIZED, Customer::query()->find($ids->first())),
            default => new self(self::AMBIGUOUS, null),
        };
    }

    public static function nobody(): self
    {
        return new self(self::UNKNOWN, null);
    }

    public function recognized(): bool
    {
        return $this->customer !== null;
    }

    public function customerId(): ?int
    {
        return $this->customer?->id;
    }

    public function firstName(): ?string
    {
        return $this->customer?->first_name;
    }

    /**
     * @return list<array{id: int, label: string, plate: ?string}>
     */
    public function vehicles(): array
    {
        return $this->ownedVehicles()
            ->map(fn (Vehicle $vehicle): array => [
                'id' => (int) $vehicle->id,
                'label' => self::vehicleLabel($vehicle),
                'plate' => filled($vehicle->plate) ? strtoupper(trim((string) $vehicle->plate)) : null,
            ])
            ->values()
            ->all();
    }

    public function vehicle(int $id): ?Vehicle
    {
        return $this->ownedVehicles()->firstWhere('id', $id);
    }

    /**
     * Open recommendations made to this customer for one of their vehicles.
     *
     * @return list<array{id: int, title: string, description: ?string, urgency: ?string, safety: bool, recommended_on: ?string}>
     */
    public function recommendations(int $vehicleId): array
    {
        return $this->openRecommendations($vehicleId)
            ->map(fn (Recommendation $recommendation): array => [
                'id' => (int) $recommendation->id,
                'title' => (string) $recommendation->title,
                'description' => filled($recommendation->customer_description) ? (string) $recommendation->customer_description : null,
                'urgency' => $recommendation->urgency?->label(),
                'safety' => (bool) $recommendation->safety_related,
                'recommended_on' => $recommendation->discovered_at?->format('M j, Y') ?? $recommendation->created_at?->format('M j, Y'),
            ])
            ->values()
            ->all();
    }

    public function recommendation(int $vehicleId, int $id): ?Recommendation
    {
        return $this->openRecommendations($vehicleId)->firstWhere('id', $id);
    }

    public static function vehicleLabel(Vehicle $vehicle): string
    {
        $label = trim(implode(' ', array_filter([$vehicle->year, $vehicle->make, $vehicle->model, $vehicle->trim], filled(...))));

        return $label !== '' ? $label : (filled($vehicle->nickname) ? (string) $vehicle->nickname : 'Vehicle');
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function ownedVehicles(): Collection
    {
        if ($this->customer === null) {
            return collect();
        }

        return Vehicle::query()
            ->where('customer_id', $this->customer->id)
            ->orderByDesc('year')
            ->orderBy('id')
            ->limit(25)
            ->get();
    }

    /**
     * @return Collection<int, Recommendation>
     */
    private function openRecommendations(int $vehicleId): Collection
    {
        if ($this->vehicle($vehicleId) === null) {
            return collect();
        }

        return Recommendation::query()
            ->where('customer_id', $this->customer?->id)
            ->where('vehicle_id', $vehicleId)
            ->where('lifecycle', RecommendationLifecycle::Open->value)
            ->orderByDesc('safety_related')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }
}
