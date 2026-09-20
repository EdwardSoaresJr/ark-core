<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\SyncConversationTurnAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallSession extends Model
{
    protected $fillable = [
        'provider',
        'provider_call_sid',
        'direction',
        'from_number',
        'to_number',
        'normalized_from',
        'normalized_to',
        'status',
        'customer_id',
        'repair_order_id',
        'started_at',
        'answered_at',
        'ended_at',
        'worked_at',
        'owned_by_user_id',
        'owned_at',
        'raw_payload',
        'recording_url',
        'recording_sid',
        'recording_duration_seconds',
        'recording_capture_status',
        'recording_capture_error',
        'recording_media_metadata',
        'voicemail_url',
        'voicemail_sid',
        'voicemail_duration_seconds',
        'voicemail_capture_status',
        'voicemail_capture_error',
        'voicemail_media_metadata',
        'analysis_status',
        'transcript',
        'analysis_json',
        'analysis_error',
        'analyzed_at',
        'coaching_follow_up_at',
    ];

    protected static function booted(): void
    {
        static::saved(function (CallSession $session): void {
            app(SyncConversationTurnAction::class)->forCallSession($session);
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => TelephonyProviderType::class,
            'direction' => CallSessionDirection::class,
            'status' => CallSessionStatus::class,
            'analysis_status' => CallSessionAnalysisStatus::class,
            'recording_capture_status' => CallSessionMediaCaptureStatus::class,
            'voicemail_capture_status' => CallSessionMediaCaptureStatus::class,
            'recording_media_metadata' => 'array',
            'voicemail_media_metadata' => 'array',
            'analysis_json' => 'array',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'worked_at' => 'datetime',
            'owned_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'coaching_follow_up_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function isCoachingFollowUpPinned(): bool
    {
        return $this->coaching_follow_up_at !== null;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by_user_id');
    }

    /**
     * @return HasMany<SessionEvent, $this>
     */
    public function sessionEvents(): HasMany
    {
        return $this->hasMany(SessionEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function hasRecording(): bool
    {
        return filled($this->recording_url);
    }

    public function hasVoicemail(): bool
    {
        return filled($this->voicemail_url);
    }

    public function mediaCaptureStatus(string $kind = 'recording'): ?CallSessionMediaCaptureStatus
    {
        return $kind === 'voicemail'
            ? $this->voicemail_capture_status
            : $this->recording_capture_status;
    }

    public function analysisMediaKind(): ?string
    {
        if ($this->hasRecording()) {
            return 'recording';
        }

        if ($this->hasVoicemail()) {
            return 'voicemail';
        }

        return null;
    }

    public function analysisDurationSeconds(): ?int
    {
        if ($this->hasRecording()) {
            return $this->recording_duration_seconds;
        }

        if ($this->hasVoicemail()) {
            return $this->voicemail_duration_seconds;
        }

        return null;
    }

    public function talkDurationLabel(): ?string
    {
        if ($this->answered_at === null || $this->ended_at === null) {
            return null;
        }

        $seconds = (int) $this->answered_at->diffInSeconds($this->ended_at);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return $remainder > 0 ? "{$minutes}m {$remainder}s" : "{$minutes}m";
    }

    public function analysisSummary(): ?string
    {
        $summary = data_get($this->analysis_json, 'summary');

        return is_string($summary) && trim($summary) !== '' ? trim($summary) : null;
    }

    public function analysisSentiment(): ?string
    {
        $sentiment = data_get($this->analysis_json, 'sentiment');

        return is_string($sentiment) && trim($sentiment) !== '' ? trim($sentiment) : null;
    }

    public function analysisFollowUpNeeded(): bool
    {
        return (bool) data_get($this->analysis_json, 'follow_up_needed', false);
    }

    public function isActivelyLive(): bool
    {
        if ($this->worked_at !== null) {
            return false;
        }

        if ($this->status === CallSessionStatus::Ringing) {
            $startedAt = $this->started_at;

            return $startedAt !== null && $startedAt->gte(now()->subMinutes(5));
        }

        if ($this->status !== CallSessionStatus::Answered || $this->ended_at !== null) {
            return false;
        }

        $answeredAt = $this->answered_at ?? $this->started_at;

        return $answeredAt !== null && $answeredAt->gte(now()->subMinutes(3));
    }

    public function directionLabel(): string
    {
        return $this->direction === CallSessionDirection::Outbound ? 'Outbound' : 'Inbound';
    }

    /**
     * @param  Builder<CallSession>  $query
     * @return Builder<CallSession>
     */
    public function scopeExcludingFeatureCodeDials(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $scoped): void {
                $scoped->whereNull('to_number')
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('to_number', 'not like', '*%')
                            ->whereNotIn('to_number', ['##', '*2']);
                    });
            })
            ->where(function (Builder $scoped): void {
                $scoped->whereNull('normalized_to')
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('normalized_to', 'not like', '*%')
                            ->whereNotIn('normalized_to', ['##', '*2']);
                    });
            })
            ->excludingExtensionLegArtifacts();
    }

    /**
     * @param  Builder<CallSession>  $query
     * @return Builder<CallSession>
     */
    public function scopeExcludingExtensionLegArtifacts(Builder $query): Builder
    {
        $extensions = TelephonyExtension::query()
            ->where('enabled', true)
            ->pluck('extension')
            ->map(fn (mixed $extension): string => trim((string) $extension))
            ->filter()
            ->values()
            ->all();

        if ($extensions === []) {
            return $query;
        }

        return $query->whereNot(function (Builder $artifact) use ($extensions): void {
            $artifact->whereNull('customer_id')
                ->where(function (Builder $leg) use ($extensions): void {
                    $leg->where(function (Builder $deskRing) use ($extensions): void {
                        $deskRing->where(function (Builder $toExtension) use ($extensions): void {
                            $toExtension->whereIn('normalized_to', $extensions)
                                ->orWhereIn('to_number', $extensions);
                        })->where(function (Builder $notFromCustomer) use ($extensions): void {
                            $notFromCustomer->whereNull('normalized_from')
                                ->orWhere('normalized_from', '')
                                ->orWhereIn('normalized_from', $extensions)
                                ->orWhereRaw('LENGTH(normalized_from) < 10');
                        });
                    })
                        ->orWhere(function (Builder $inboundFromDesk) use ($extensions): void {
                            $inboundFromDesk->where('direction', CallSessionDirection::Inbound->value)
                                ->whereIn('normalized_from', $extensions);
                        })
                        ->orWhere(function (Builder $deskCompanion) use ($extensions): void {
                            $deskCompanion->where('direction', CallSessionDirection::Outbound->value)
                                ->whereIn('normalized_from', $extensions)
                                ->whereRaw('LENGTH(normalized_to) >= 10');
                        });
                });
        });
    }
}
