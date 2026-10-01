<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSmsIntelligenceSlice extends Model
{
    protected $fillable = [
        'conversation_id',
        'activity_date',
        'last_message_at',
        'message_count',
        'inbound_count',
        'outbound_count',
        'transcript',
        'analysis_status',
        'analysis_json',
        'analysis_error',
        'analyzed_at',
        'coaching_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'last_message_at' => 'datetime',
            'analysis_status' => CallSessionAnalysisStatus::class,
            'analysis_json' => 'array',
            'analyzed_at' => 'datetime',
            'coaching_follow_up_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isCoachingFollowUpPinned(): bool
    {
        return $this->coaching_follow_up_at !== null;
    }

    public function analysisSummary(): ?string
    {
        $summary = data_get($this->analysis_json, 'summary');

        return is_string($summary) && trim($summary) !== '' ? trim($summary) : null;
    }

    public function analysisSentiment(): ?string
    {
        $sentiment = data_get($this->analysis_json, 'sentiment');

        return is_string($sentiment) && $sentiment !== '' ? $sentiment : null;
    }

    public function analysisFollowUpNeeded(): bool
    {
        return (bool) data_get($this->analysis_json, 'follow_up_needed', false);
    }

    public function analysisIsStale(): bool
    {
        if ($this->analyzed_at === null || $this->last_message_at === null) {
            return false;
        }

        return $this->last_message_at->gt($this->analyzed_at);
    }

    public function advisorFollowUpApplies(): bool
    {
        if ($this->analysis_status !== CallSessionAnalysisStatus::Ready) {
            return false;
        }

        if (! $this->analysisFollowUpNeeded()) {
            return false;
        }

        return ! $this->analysisIsStale();
    }

    public function analysisSuggestedReply(): ?string
    {
        $suggested = data_get($this->analysis_json, 'suggested_reply');

        return is_string($suggested) && trim($suggested) !== '' ? trim($suggested) : null;
    }

    public function needsSuggestedReplyRefresh(): bool
    {
        if ($this->analysis_status !== CallSessionAnalysisStatus::Ready) {
            return false;
        }

        if (! $this->analysisFollowUpNeeded()) {
            return false;
        }

        return $this->analysisSuggestedReply() === null;
    }
}
