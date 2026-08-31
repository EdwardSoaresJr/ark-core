<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyEndpoint extends Model
{
    protected $fillable = [
        'name',
        'type',
        'destination',
        'user_id',
        'ring_schedule',
        'ring_delay_seconds',
        'presence_timeout_minutes',
        'enabled',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => TelephonyEndpointType::class,
            'ring_schedule' => TelephonyRingSchedule::class,
            'enabled' => 'boolean',
            'position' => 'integer',
            'ring_delay_seconds' => 'integer',
            'presence_timeout_minutes' => 'integer',
        ];
    }

    public function presenceTimeoutMinutes(): int
    {
        return max(5, min(240, (int) ($this->presence_timeout_minutes ?? 30)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dialDestination(): string
    {
        if ($this->type === TelephonyEndpointType::Sip) {
            return TelephonySipDestination::normalize($this->destination);
        }

        if ($this->type === TelephonyEndpointType::MobileApp) {
            return trim((string) $this->destination);
        }

        $fromUser = $this->resolvedUserPhone();

        if ($fromUser !== '') {
            return $fromUser;
        }

        return PhoneNumber::normalize($this->destination) ?? PhoneNumber::digits($this->destination);
    }

    public function resolvedUserPhone(): string
    {
        if ($this->user_id === null) {
            return '';
        }

        $this->loadMissing('user');

        return PhoneNumber::normalize($this->user?->phone) ?? '';
    }

    public function toTwimlChild(
        ?string $legStatusCallbackUrl = null,
        ?string $parentCallSid = null,
        bool $enableMachineDetection = true,
    ): string {
        $rawDestination = $this->dialDestination();
        $destination = htmlspecialchars(
            $this->type === TelephonyEndpointType::Cell
                ? (PhoneNumber::toE164($rawDestination) ?? $rawDestination)
                : $rawDestination,
            ENT_XML1,
        );
        $legCallback = '';

        if ($legStatusCallbackUrl !== null && $legStatusCallbackUrl !== '') {
            $events = match ($this->type) {
                TelephonyEndpointType::Sip, TelephonyEndpointType::MobileApp => 'answered',
                default => 'answered in-progress',
            };

            $legCallback = ' statusCallback="'.htmlspecialchars($legStatusCallbackUrl, ENT_XML1).'"'
                .' statusCallbackEvent="'.$events.'" statusCallbackMethod="POST"';
        }

        return match ($this->type) {
            TelephonyEndpointType::Cell => $this->cellTwimlChild($destination, $legCallback, $parentCallSid),
            TelephonyEndpointType::Sip => "<Sip{$legCallback}>{$destination}</Sip>",
            TelephonyEndpointType::MobileApp => '<Client'.$legCallback.'>'.$destination.'</Client>',
        };
    }

    private function cellTwimlChild(
        string $destination,
        string $legCallback,
        ?string $parentCallSid,
    ): string {
        $whisperUrl = '';

        if ($parentCallSid !== null && $parentCallSid !== '' && $this->id > 0) {
            $whisperUrl = ' url="'.htmlspecialchars(
                '',
                ENT_XML1,
            ).'"';
        }

        return '<Number answerOnBridge="true"'.$whisperUrl.$legCallback.'>'.$destination.'</Number>';
    }
}
