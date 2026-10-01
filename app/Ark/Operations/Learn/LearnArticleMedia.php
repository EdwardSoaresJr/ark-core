<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnArticleMedia extends Model
{
    protected $table = 'learn_article_media';

    protected $fillable = [
        'article_key',
        'slot',
        'kind',
        'storage_path',
        'mime_type',
        'youtube_video_id',
        'original_name',
        'uploaded_by_user_id',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function isYoutube(): bool
    {
        return $this->kind === 'youtube';
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }
}
