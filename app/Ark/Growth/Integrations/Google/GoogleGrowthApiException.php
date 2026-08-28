<?php

namespace App\Ark\Growth\Integrations\Google;

use RuntimeException;

final class GoogleGrowthApiException extends RuntimeException
{
    public static function fromResponse(string $action, int $status, string $body): self
    {
        return new self(self::friendlyMessage($action, $status, $body));
    }

    private static function friendlyMessage(string $action, int $status, string $body): string
    {
        $decoded = json_decode($body, true);
        $error = is_array($decoded) ? ($decoded['error'] ?? []) : [];
        $details = is_array($error['details'] ?? null) ? $error['details'] : [];
        $googleMessage = trim((string) ($error['message'] ?? ''));

        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $service = (string) ($detail['metadata']['service'] ?? '');
            $quotaLimit = (string) ($detail['metadata']['quota_limit_value'] ?? '');
            $reason = (string) ($detail['reason'] ?? '');

            if ($reason === 'RATE_LIMIT_EXCEEDED' && $quotaLimit === '0') {
                return self::apiNotEnabledMessage($service);
            }
        }

        if ($status === 403 && str_contains(strtolower($googleMessage), 'has not been used')) {
            return self::apiNotEnabledMessage('');
        }

        if ($status === 429) {
            return 'Google rate limit hit. Wait one minute, then click Discover locations once. If this keeps happening, confirm the Business Profile APIs are enabled and allowlisted on your Google Cloud project.';
        }

        if ($status === 403) {
            return 'Google denied access while trying to '.$action.'. Add the active Growth service account as a Manager on the Lugs N Plugs listing in Google Business Profile, then try again.';
        }

        if ($googleMessage !== '') {
            return 'Google could not '.$action.': '.$googleMessage;
        }

        return 'Google could not '.$action.' (HTTP '.$status.').';
    }

    private static function apiNotEnabledMessage(string $service): string
    {
        $apiHint = match (true) {
            str_contains($service, 'accountmanagement') => 'My Business Account Management API',
            str_contains($service, 'businessinformation') => 'My Business Business Information API',
            str_contains($service, 'businessprofileperformance') => 'Business Profile Performance API',
            default => 'My Business Account Management API',
        };

        return $apiHint.' is not enabled or not allowlisted on your Google Cloud project (quota is 0). Enable all three Business Profile APIs, confirm API access was approved for this project, wait 5–10 minutes, then click Discover locations again.';
    }
}
