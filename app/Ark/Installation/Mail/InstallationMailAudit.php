<?php

namespace App\Ark\Installation\Mail;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class InstallationMailAudit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(?User $actor, string $action, string $summary, array $context = []): void
    {
        if (! Schema::hasTable('installation_mail_audit_events')) {
            return;
        }

        // Never persist secrets.
        unset($context['password'], $context['installation_mail_password']);

        InstallationMailAuditEvent::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * @return list<InstallationMailAuditEvent>
     */
    public function recent(int $limit = 20): array
    {
        if (! Schema::hasTable('installation_mail_audit_events')) {
            return [];
        }

        return InstallationMailAuditEvent::query()
            ->with('actor:id,name,email')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->all();
    }
}
