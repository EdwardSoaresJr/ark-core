<?php

namespace App\Ark\Operations\Booking\Contracts;

/**
 * The customer's proof of identity, spent by OnlineBooking inside its booking transaction, after
 * every check passed and before anything is recorded. Spending must be atomic (only one caller
 * holding the same proof can succeed) and use the shop's database, so a booking that fails after
 * spending it rolls the spend back with everything else.
 */
interface BookingProof
{
    /**
     * False when the proof was already spent, has expired, or no longer matches the contact.
     */
    public function consume(): bool;
}
