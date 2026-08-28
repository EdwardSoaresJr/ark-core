<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Platform\VoiceTransportConfiguration;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shop people ARK Phone may blind-transfer to — SIP REFER uses refer_uri.
 */
final class MobilePhonePeopleProjection
{
    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     refer_uri: string,
     * }>
     */
    public function forUser(User $user): array
    {
        $ownMobileExtension = TelephonyExtension::query()
            ->where('user_id', $user->id)
            ->where('device_type', TelephonyExtensionDeviceType::MobileApp)
            ->where('enabled', true)
            ->value('extension');

        $registrar = VoiceTransportConfiguration::sipRegistrar();

        $extensions = TelephonyExtension::query()
            ->with(['user', 'workstation'])
            ->where('enabled', true)
            ->when($ownMobileExtension !== null, fn ($query) => $query->where('extension', '!=', $ownMobileExtension))
            ->orderBy('extension')
            ->get();

        return $this->dedupeForTransfer($extensions)
            ->map(fn (TelephonyExtension $extension) => [
                'id' => 'ext-'.$extension->extension,
                'name' => $this->personName($extension),
                'refer_uri' => 'sip:'.$extension->extension.'@'.$registrar,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TelephonyExtension>  $extensions
     * @return Collection<int, TelephonyExtension>
     */
    private function dedupeForTransfer(Collection $extensions): Collection
    {
        $rank = [
            TelephonyExtensionDeviceType::DeskPhone->value => 0,
            TelephonyExtensionDeviceType::Softphone->value => 1,
            TelephonyExtensionDeviceType::Other->value => 2,
            TelephonyExtensionDeviceType::Tablet->value => 3,
            TelephonyExtensionDeviceType::PageGroup->value => 4,
            TelephonyExtensionDeviceType::MobileApp->value => 5,
        ];

        $chosen = [];

        foreach ($extensions as $extension) {
            $userId = $extension->user_id;

            if ($userId === null) {
                $chosen['orphan-'.$extension->extension] = $extension;

                continue;
            }

            $key = 'user-'.$userId;
            $existing = $chosen[$key] ?? null;

            if ($existing === null) {
                $chosen[$key] = $extension;

                continue;
            }

            $currentRank = $rank[$extension->device_type->value] ?? 99;
            $existingRank = $rank[$existing->device_type->value] ?? 99;

            if ($currentRank < $existingRank) {
                $chosen[$key] = $extension;
            }
        }

        return collect($chosen)->sortBy(fn (TelephonyExtension $extension) => $this->personName($extension))->values();
    }

    private function personName(TelephonyExtension $extension): string
    {
        $userName = trim((string) ($extension->user?->name ?? ''));

        if ($userName !== '') {
            $parts = preg_split('/\s+/', $userName) ?: [];

            return $parts[0] !== '' ? $parts[0] : $userName;
        }

        $displayName = trim((string) ($extension->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        $workstationName = trim((string) ($extension->workstation?->name ?? ''));

        if ($workstationName !== '') {
            return $workstationName;
        }

        return 'Shop line';
    }
}
