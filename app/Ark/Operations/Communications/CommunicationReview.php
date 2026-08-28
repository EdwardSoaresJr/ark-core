<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationReview extends Model
{
    protected $fillable = [
        'call_session_id',
        'conversation_sms_intelligence_slice_id',
        'advisor_user_id',
        'composite_score',
        'coaching_opportunity_weight',
        'strengths',
        'opportunities',
        'dimension_scores',
        'reviewed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'source' => CommunicationReviewSource::class,
            'strengths' => 'array',
            'opportunities' => 'array',
            'dimension_scores' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function smsSlice(): BelongsTo
    {
        return $this->belongsTo(ConversationSmsIntelligenceSlice::class, 'conversation_sms_intelligence_slice_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }
}
