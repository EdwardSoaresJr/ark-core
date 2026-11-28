<?php

namespace App\Ark\Installation\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationMailAuditEvent extends Model
{
    protected $table = 'installation_mail_audit_events';

    protected $fillable = [
        'actor_id',
        'action',
        'summary',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
