<?php

namespace App\Ark\Station;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class StationGlassConfig
{
    /**
     * @return array<string, mixed>
     */
    public function forToken(StationDeviceToken $token): array
    {
        $raw = is_array($token->glass_config) ? $token->glass_config : [];
        $mode = ($raw['advisor_mode'] ?? 'two') === 'one' ? 'one' : 'two';
        $appearance = match ($raw['appearance'] ?? 'light') {
            'dark' => 'dark',
            'system' => 'system',
            default => 'light',
        };
        $primaryId = isset($raw['primary_advisor_user_id']) ? (int) $raw['primary_advisor_user_id'] : null;
        $secondaryId = isset($raw['secondary_advisor_user_id']) ? (int) $raw['secondary_advisor_user_id'] : null;

        if ($mode === 'one') {
            $secondaryId = null;
        }

        $eligible = $this->eligibleAdvisors();
        $eligibleIds = collect($eligible)->pluck('id')->all();

        if ($primaryId !== null && ! in_array($primaryId, $eligibleIds, true)) {
            $primaryId = null;
        }
        if ($secondaryId !== null && ! in_array($secondaryId, $eligibleIds, true)) {
            $secondaryId = null;
        }

        return [
            'appearance' => $appearance,
            'advisor_mode' => $mode,
            'primary_advisor_user_id' => $primaryId,
            'secondary_advisor_user_id' => $secondaryId,
            'workstation_id' => isset($raw['workstation_id']) ? (int) $raw['workstation_id'] : null,
            'eligible_advisors' => $eligible,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateToken(StationDeviceToken $token, array $input): array
    {
        $current = $this->forToken($token);
        $next = [
            'appearance' => match ($input['appearance'] ?? $current['appearance']) {
                'dark' => 'dark',
                'system' => 'system',
                default => 'light',
            },
            'advisor_mode' => ($input['advisor_mode'] ?? $current['advisor_mode']) === 'one' ? 'one' : 'two',
            'primary_advisor_user_id' => $input['primary_advisor_user_id'] ?? $current['primary_advisor_user_id'],
            'secondary_advisor_user_id' => $input['secondary_advisor_user_id'] ?? $current['secondary_advisor_user_id'],
            'workstation_id' => $input['workstation_id'] ?? $current['workstation_id'],
        ];

        $token->forceFill(['glass_config' => $next])->save();

        return $this->forToken($token->fresh());
    }

    /**
     * @return list<array{id: int, name: string, accent: string}>
     */
    public function eligibleAdvisors(): array
    {
        return User::query()
            ->active()
            ->role([ArkRole::Admin->value, ArkRole::Advisor->value])
            ->orderBy('name')
            ->get(['id', 'name', 'accent_color', 'accent_theme'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'accent' => $user->accentHexResolved(),
            ])
            ->values()
            ->all();
    }
}
