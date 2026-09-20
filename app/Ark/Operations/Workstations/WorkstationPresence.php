<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class WorkstationPresence
{
    public const BINDING_COOKIE = 'ark_workstation_binding';

    public const DISMISS_COOKIE = 'ark_workstation_bind_dismissed';

    public const SESSION_LOCKED = 'operations.workstation_locked';

    public const SESSION_BIND_DISMISSED = 'operations.workstation_bind_dismissed';

    public function __construct(
        public ?Workstation $workstation,
        public ?User $currentOperator,
        public bool $locked,
        public bool $needsBinding,
        public bool $needsUnlock,
        public bool $needsPinSetup,
        public bool $canManagePresence,
    ) {}

    public static function resolve(Request $request): self
    {
        $user = $request->user();
        $canManage = $user !== null && $user->can('operations.access');

        if (! $canManage) {
            return new self(null, null, false, false, false, false, false);
        }

        $request->session()->forget(self::SESSION_LOCKED);

        $workstation = self::resolveBoundWorkstation($request);
        $needsBinding = $workstation === null && ! self::bindPromptDismissed($request);
        $currentOperator = $workstation?->currentOperator;

        return new self(
            workstation: $workstation,
            currentOperator: $currentOperator,
            locked: false,
            needsBinding: $needsBinding,
            needsUnlock: false,
            needsPinSetup: false,
            canManagePresence: true,
        );
    }

    public static function bindPromptDismissed(Request $request): bool
    {
        if ((bool) $request->session()->get(self::SESSION_BIND_DISMISSED, false)) {
            return true;
        }

        return (string) $request->cookie(self::DISMISS_COOKIE, '') === '1';
    }

    public static function resolveBinding(Request $request): ?WorkstationBrowserBinding
    {
        $token = (string) $request->cookie(self::BINDING_COOKIE, '');

        if ($token === '') {
            return null;
        }

        return WorkstationBrowserBinding::query()
            ->where('token', $token)
            ->first();
    }

    public static function resolveBoundWorkstation(Request $request): ?Workstation
    {
        $token = (string) $request->cookie(self::BINDING_COOKIE, '');

        if ($token === '') {
            return null;
        }

        $binding = WorkstationBrowserBinding::query()
            ->with(['workstation.currentOperator', 'workstation.primaryTelephonyExtension'])
            ->where('token', $token)
            ->first();

        if (! $binding instanceof WorkstationBrowserBinding) {
            return null;
        }

        $workstation = $binding->workstation;

        if (! $workstation instanceof Workstation || ! $workstation->is_active) {
            return null;
        }

        $binding->touchSeen();

        return $workstation;
    }

    public static function bindingCookie(WorkstationBrowserBinding $binding): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            name: self::BINDING_COOKIE,
            value: $binding->token,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public static function bindDismissedCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            name: self::DISMISS_COOKIE,
            value: '1',
            minutes: 60 * 24 * 365,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public static function forgetBindingCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forget(self::BINDING_COOKIE);
    }

    public static function forgetBindDismissedCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forget(self::DISMISS_COOKIE);
    }

    /**
     * Station lock screen retired — ARK session stays usable at bound stations.
     */
    public function operationalPrivacyActive(): bool
    {
        return false;
    }

    /**
     * Comms interrupt popups must not surface customer context during station privacy.
     */
    public function suppressesCommsInterrupts(): bool
    {
        return $this->operationalPrivacyActive();
    }
}
