<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Collection;

/**
 * Everything the public booking form shows, from the shop's saved settings and the scheduler.
 */
final class BookingFormProjection
{
    public function __construct(
        private readonly BookingAvailability $availability,
        private readonly SubmitBooking $submit,
        private readonly BookingIdentity $identity,
    ) {}

    /**
     * @param  array<string, mixed>  $input  current values (query prefill on first view, posted values after)
     * @return array<string, mixed>
     */
    public function build(array $input, ?Customer $portalCustomer = null): array
    {
        $settings = BookingSettings::forShop();
        $services = BookingService::query()->offeredPublicly()->ordered()->get();
        $questions = BookingQuestion::query()->active()->get();

        $service = $this->selectedService($services, $input);
        $optionLabel = is_string($input['service_option'] ?? null) ? $input['service_option'] : null;
        $location = $this->submit->locationFor($service, (string) ($input['location'] ?? ''));
        $vehicleRows = is_array($input['vehicles'] ?? null) ? array_values($input['vehicles']) : [];
        $context = new BookingContext(
            $service,
            $service?->option($optionLabel)['label'] ?? null,
            max(1, min($settings['max_vehicles'], count(array_filter($vehicleRows, fn ($v): bool => is_array($v) && array_filter($v) !== [])))),
            $location,
            is_array($input['address'] ?? null) ? array_map(fn ($v): string => (string) $v, $input['address']) : [],
        );

        $availability = $this->availability->forContext($context);
        $mode = BookingMode::from($availability['mode']);
        $date = $this->selectedDate($availability['dates'], (string) ($input['preferred_date'] ?? ''));
        $periods = $date !== null && $mode === BookingMode::Request ? $this->availability->periodsFor($date) : [];
        $slots = $date !== null && $mode === BookingMode::Instant ? $this->availability->slotsFor($date, $context) : [];

        $recognition = $portalCustomer !== null ? app(CustomerRecognitionProjection::class)->forCustomer($portalCustomer) : null;

        return [
            'settings' => $settings,
            'shop' => ShopSettings::current(),
            'mode' => $mode,
            'services' => $services->map(fn (BookingService $s): array => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'description' => (string) $s->description,
                'duration_label' => AppointmentSlotMinutes::durationLabel((int) $s->duration_minutes),
                'location_mode' => $s->locationMode()->value,
                'requires_approval' => (bool) $s->requires_approval,
                'concern_prompt' => (string) $s->concern_prompt,
                'options' => $s->optionList(),
            ])->all(),
            'selectedService' => $service?->id,
            'serviceModes' => $services->mapWithKeys(fn (BookingService $s): array => [$s->id => (new BookingContext($s))->mode($settings)->value])->all(),
            'location' => $location,
            'questions' => $questions->map(fn (BookingQuestion $q): array => [
                'id' => $q->id,
                'label' => $q->label,
                'help' => (string) $q->help,
                'type' => $q->type->value,
                'choices' => $q->choiceList(),
                'required' => (bool) $q->required,
                'service_ids' => $q->serviceIdList(),
            ])->all(),
            'availability' => $availability,
            'selectedDate' => $date,
            'periods' => $periods,
            'slots' => $slots,
            'contactPreferences' => LeadContactPreference::cases(),
            'portalVehicles' => $recognition['vehicles'] ?? [],
            'portalContact' => [
                'name' => trim((string) ($recognition['customer']['first_name'] ?? '').' '.($recognition['customer']['last_name'] ?? '')),
                'phone' => (string) ($recognition['customer']['phone'] ?? ''),
                'email' => (string) ($recognition['customer']['email'] ?? ''),
            ],
            'phoneVerificationReady' => $this->identity->phoneReady(),
            'emailVerificationReady' => $this->identity->emailReady(),
        ];
    }

    /**
     * @param  Collection<int, BookingService>  $services
     * @param  array<string, mixed>  $input
     */
    private function selectedService($services, array $input): ?BookingService
    {
        if ($services->isEmpty()) {
            return null;
        }

        $wanted = trim((string) ($input['service_id'] ?? $input['service'] ?? ''));
        if ($wanted !== '') {
            $match = $services->first(fn (BookingService $s): bool => (string) $s->id === $wanted || strcasecmp($s->slug, $wanted) === 0);
            if ($match !== null) {
                return $match;
            }
        }

        $concern = mb_strtolower(trim((string) ($input['concern_choice'] ?? '') ?: (string) ($input['concern'] ?? '')));
        if ($concern !== '') {
            $match = $services->first(fn (BookingService $s): bool => str_contains($concern, mb_strtolower($s->name)) || str_contains(mb_strtolower($s->name), $concern));
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param  list<array{date: string, available: bool}>  $dates
     */
    private function selectedDate(array $dates, string $wanted): ?string
    {
        foreach ($dates as $row) {
            if ($row['available'] && $row['date'] === $wanted) {
                return $wanted;
            }
        }

        foreach ($dates as $row) {
            if ($row['available']) {
                return $row['date'];
            }
        }

        return null;
    }
}
