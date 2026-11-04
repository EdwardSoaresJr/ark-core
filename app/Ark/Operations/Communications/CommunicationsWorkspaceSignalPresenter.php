<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Support\Carbon;

/**
 * Words and badge tones for the workspace's signals, whoever supplied them.
 */
final class CommunicationsWorkspaceSignalPresenter
{
    private const FAILED = ['undelivered', 'failed', 'canceled'];

    /**
     * @param  array{status: string, error_code?: string|null, at?: \DateTimeInterface|null}  $delivery
     * @return array{status: string, label: string, tone: string, failed: bool, detail: ?string, at_label: ?string}
     */
    public static function delivery(array $delivery): array
    {
        $status = strtolower(trim($delivery['status']));
        $failed = in_array($status, self::FAILED, true);
        $error = trim((string) ($delivery['error_code'] ?? ''));
        $at = $delivery['at'] ?? null;

        return [
            'status' => $status,
            'label' => match ($status) {
                'accepted', 'scheduled', 'queued' => 'Queued',
                'sending' => 'Sending',
                'sent' => 'Sent',
                'partially_delivered' => 'Partly delivered',
                'delivered' => 'Delivered',
                'read' => 'Read',
                'canceled' => 'Canceled',
                default => $failed ? 'Not delivered' : ucfirst(str_replace('_', ' ', $status)),
            },
            'tone' => match (true) {
                $failed => 'danger',
                in_array($status, ['delivered', 'read'], true) => 'success',
                default => 'secondary',
            },
            'failed' => $failed,
            'detail' => $failed
                ? trim(($error !== '' ? 'Carrier error '.$error.'. ' : '').'The customer did not get this message; call or send it again.')
                : null,
            'at_label' => $at instanceof \DateTimeInterface
                ? Carbon::instance($at)->timezone(config('app.display_timezone'))->format('M j, g:i A')
                : null,
        ];
    }

    /**
     * @param  list<array{label: string, tone?: string, title?: string|null}>  $notes
     * @return list<array{label: string, tone: string, title: ?string}>
     */
    public static function messageNotes(array $notes): array
    {
        return array_values(array_map(fn (array $note): array => [
            'label' => (string) $note['label'],
            'tone' => in_array($note['tone'] ?? null, ['success', 'warning', 'danger'], true) ? $note['tone'] : 'secondary',
            'title' => filled($note['title'] ?? null) ? (string) $note['title'] : null,
        ], array_filter($notes, fn (mixed $note): bool => is_array($note) && filled($note['label'] ?? null))));
    }

    /**
     * @param  array{texting: string, source?: string|null, quiet_hours_until?: \DateTimeInterface|null, notes?: list<string>}  $permission
     * @return array{texting: string, label: string, tone: string, detail: ?string, notes: list<string>}
     */
    public static function permission(array $permission): array
    {
        $texting = $permission['texting'];
        $quietUntil = $permission['quiet_hours_until'] ?? null;
        $quiet = $quietUntil instanceof \DateTimeInterface
            ? 'Quiet hours: automated texts wait until '.Carbon::instance($quietUntil)->timezone(config('app.display_timezone'))->format('g:i A').'.'
            : null;

        return [
            'texting' => $texting,
            'label' => match ($texting) {
                'opted_out' => 'Texting off',
                'allowed' => 'Texting allowed',
                default => 'Texting not confirmed',
            },
            'tone' => match ($texting) {
                'opted_out' => 'danger',
                'allowed' => 'success',
                default => 'secondary',
            },
            'detail' => trim(implode(' ', array_filter([
                $texting === 'opted_out' ? trim(($permission['source'] ?? 'The customer opted out').'. Texts are not sent until they reply START.') : null,
                $quiet,
            ]))) ?: null,
            'notes' => array_values(array_filter(array_map('strval', $permission['notes'] ?? []), fn (string $note): bool => trim($note) !== '')),
        ];
    }
}
