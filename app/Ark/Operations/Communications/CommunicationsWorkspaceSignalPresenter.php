<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Messaging\SmsDeliveryFailureKind;
use Illuminate\Support\Carbon;

/**
 * Words and badge tones for the workspace's signals, whoever supplied them.
 */
final class CommunicationsWorkspaceSignalPresenter
{
    private const FAILED = ['undelivered', 'failed', 'canceled'];

    /**
     * @param  array{status: string, error_code?: string|null, at?: \DateTimeInterface|null}  $delivery
     * @return array{status: string, label: string, tone: string, failed: bool, detail: ?string, at_label: ?string, allows_retry: bool}
     */
    public static function delivery(array $delivery): array
    {
        $status = strtolower(trim($delivery['status']));
        $failed = in_array($status, self::FAILED, true);
        $error = trim((string) ($delivery['error_code'] ?? ''));
        $at = $delivery['at'] ?? null;
        $kind = $failed ? SmsDeliveryFailureKind::fromErrorCode($error !== '' ? $error : null) : null;

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
            'detail' => $failed ? ($kind?->staffDetail($error !== '' ? $error : null) ?? SmsDeliveryFailureKind::Unknown->staffDetail(null)) : null,
            'allows_retry' => $kind?->allowsRetry() ?? true,
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
     * @param  array{
     *     texting: string,
     *     source?: string|null,
     *     unavailable_reason?: 'landline'|'service_disabled'|'unsupported'|null,
     *     quiet_hours_until?: \DateTimeInterface|null,
     *     notes?: list<string>
     * }  $permission
     * @return array{texting: string, label: string, tone: string, detail: ?string, notes: list<string>}
     */
    public static function permission(array $permission): array
    {
        $texting = self::normalizeTexting((string) ($permission['texting'] ?? 'unknown'), $permission);
        $quietUntil = $permission['quiet_hours_until'] ?? null;
        $quiet = $quietUntil instanceof \DateTimeInterface
            ? 'Quiet hours: automated texts wait until '.Carbon::instance($quietUntil)->timezone(config('app.display_timezone'))->format('g:i A').'.'
            : null;
        $source = trim((string) ($permission['source'] ?? ''));
        $reason = $permission['unavailable_reason'] ?? null;

        $label = match ($texting) {
            'opted_out' => 'Texting off',
            'allowed' => 'Texting allowed',
            'unavailable' => match ($reason) {
                'landline' => 'Texting unavailable (landline)',
                'service_disabled' => 'Texting unavailable',
                default => 'Texting unavailable',
            },
            default => 'Texting not confirmed',
        };

        $detail = match ($texting) {
            'opted_out' => trim(($source !== '' ? $source : 'The customer opted out').'. Texts are not sent until they reply START.'),
            'unavailable' => match ($reason) {
                'landline' => $source !== '' ? $source : 'This number cannot receive texts. Call or use another contact.',
                'service_disabled' => $source !== '' ? $source : 'Shop texting is not available for this destination.',
                default => $source !== '' ? $source : 'Texting is unavailable for this destination. Call or use another contact.',
            },
            default => null,
        };

        return [
            'texting' => $texting,
            'label' => $label,
            'tone' => match ($texting) {
                'opted_out' => 'danger',
                'allowed' => 'success',
                'unavailable' => 'warning',
                default => 'secondary',
            },
            'detail' => trim(implode(' ', array_filter([$detail, $quiet]))) ?: null,
            'notes' => array_values(array_filter(array_map('strval', $permission['notes'] ?? []), fn (string $note): bool => trim($note) !== '')),
        ];
    }

    /**
     * @param  array{source?: string|null, unavailable_reason?: string|null, notes?: list<string>}  $permission
     */
    private static function normalizeTexting(string $texting, array $permission): string
    {
        if (in_array($texting, ['allowed', 'opted_out', 'unavailable', 'unknown'], true) && $texting !== 'unknown') {
            return $texting;
        }

        // Hosts that still emit unknown for landline/service-disabled: promote when reason is present.
        $reason = $permission['unavailable_reason'] ?? null;
        if (in_array($reason, ['landline', 'service_disabled', 'unsupported'], true)) {
            return 'unavailable';
        }

        $source = strtolower(trim((string) ($permission['source'] ?? '')));
        if ($source !== '' && (
            str_contains($source, 'landline')
            || str_contains($source, 'cannot receive')
            || str_contains($source, 'texting unavailable')
            || str_contains($source, 'not available')
            || str_contains($source, 'shop texting')
        )) {
            return 'unavailable';
        }

        foreach ($permission['notes'] ?? [] as $note) {
            $n = strtolower((string) $note);
            if (str_contains($n, 'landline') || str_contains($n, 'hosted messaging is off') || str_contains($n, 'cannot receive texts')) {
                return 'unavailable';
            }
        }

        return in_array($texting, ['allowed', 'opted_out', 'unavailable', 'unknown'], true) ? $texting : 'unknown';
    }
}
