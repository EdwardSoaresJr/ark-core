<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Booking\Contracts\AdvisoryBookingConstraint;
use App\Ark\Operations\Booking\Contracts\BookingAvailabilityConstraint;
use App\Ark\Operations\Booking\Contracts\BookingCandidate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the service area check and every tagged constraint for one availability evaluation.
 *
 * A constraint that throws is reported once and not asked again during this evaluation, so a maps
 * outage costs one failed call, not one per day and start time. When the failed check is required,
 * unavailable() names it until the evaluation ends, and callers must not confirm a time.
 */
final class BookingConstraints
{
    public const TAG = 'ark.booking.constraints';

    /** @var array<class-string, true> */
    private array $failed = [];

    /** @var array<class-string, string> */
    private array $unavailable = [];

    /**
     * A reason to show the customer when a check refuses the candidate, otherwise null.
     */
    public function refuse(BookingCandidate $candidate): ?string
    {
        foreach ($this->constraints() as $constraint) {
            if (isset($this->failed[$constraint::class])) {
                continue;
            }

            try {
                $reason = $constraint->refuse($candidate);
            } catch (Throwable $e) {
                $this->fail($constraint, $e, $candidate);

                continue;
            }

            if (is_string($reason) && trim($reason) !== '') {
                return trim($reason);
            }
        }

        return null;
    }

    /**
     * Whether a time may be confirmed for the candidate: nothing refused it and every required check ran.
     */
    public function confirms(BookingCandidate $candidate): bool
    {
        return $this->refuse($candidate) === null && $this->unavailable === [];
    }

    /**
     * Labels of required checks that could not run during this evaluation.
     *
     * @return list<string>
     */
    public function unavailable(): array
    {
        return array_values(array_unique($this->unavailable));
    }

    private function fail(BookingAvailabilityConstraint $constraint, Throwable $e, BookingCandidate $candidate): void
    {
        $required = ! $constraint instanceof AdvisoryBookingConstraint;
        $this->failed[$constraint::class] = true;

        try {
            $label = trim($constraint->label());
        } catch (Throwable) {
            $label = '';
        }
        $label = $label !== '' ? $label : class_basename($constraint);

        if ($required) {
            $this->unavailable[$constraint::class] = $label;
        }

        Log::warning('Online booking check could not run.', [
            'constraint' => $constraint::class,
            'required' => $required,
            'date' => $candidate->date,
            'service' => $candidate->context->serviceReference ?? $candidate->context->serviceName,
            'location' => $candidate->context->location->value,
            'error' => $e->getMessage(),
        ]);
        report($e);
    }

    /**
     * @return iterable<BookingAvailabilityConstraint>
     */
    private function constraints(): iterable
    {
        yield app(ServiceAreaConstraint::class);

        foreach (app()->tagged(self::TAG) as $constraint) {
            if ($constraint instanceof BookingAvailabilityConstraint) {
                yield $constraint;
            }
        }
    }
}
