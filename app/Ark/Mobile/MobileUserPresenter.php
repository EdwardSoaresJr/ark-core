<?php

namespace App\Ark\Mobile;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Ecosystem\EcosystemProduct;
use App\Ark\Runtime\Ecosystem\EcosystemSwitcherProjection;
use App\Models\User;

final class MobileUserPresenter
{
    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly MobileTelephonyDialProjection $telephonyDialProjection,
        private readonly EcosystemSwitcherProjection $ecosystemSwitcher,
        private readonly MobileCompanionShellProjection $companionShell,
    ) {}

    /**
     * App shell payload — Flutter shapes navigation from this, not hardcoded roles.
     *
     * @return array{
     *     user: array<string, mixed>,
     *     roles: list<string>,
     *     permissions: list<string>,
     *     home_profile: string,
     *     home_question: string,
     *     capabilities: array<string, bool>,
     *     navigation: list<array{key: string, label: string, enabled: bool}>,
     *     telephony: array<string, mixed>,
     *     theme: array<string, mixed>,
     *     learning: array<string, mixed>,
     * }
     */
    public function presentShell(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'display_phone' => $user->display_phone,
                'role_labels' => $user->staffRoleLabels(),
            ],
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'home_profile' => $this->homeProfile($user),
            'home_question' => $this->homeQuestion($user),
            'capabilities' => $this->capabilities($user),
            'navigation' => $this->navigation($user),
            'telephony' => $this->telephonyDialProjection->shellTelephony($user),
            'theme' => [
                'accent_color' => $user->accentHexResolved(),
                'display_mode' => $user->displayTheme()->value,
                'accent_theme' => $user->accentTheme()->value,
            ],
            'learning' => $this->learning($user),
            'companion' => $this->companionShell->forUser($user, $this->operationalProfile($user)),
        ];
    }

    /**
     * ARKademy is consumed, not rebuilt: the app links to the existing BookStack
     * instance. Access + URL reuse the ecosystem switcher authority so mobile and
     * web agree on who sees ARKademy and where it lives (per-shop configuration).
     *
     * @return array{arkademy_enabled: bool, arkademy_url: string|null}
     */
    private function learning(User $user): array
    {
        $arkademy = collect($this->ecosystemSwitcher->forUser($user))
            ->firstWhere('id', EcosystemProduct::Arkademy->value);

        return [
            'arkademy_enabled' => $arkademy !== null,
            'arkademy_url' => $arkademy['url'] ?? null,
        ];
    }

    public function operationalProfile(User $user): string
    {
        if ($user->hasRole(ArkRole::Admin->value) && $this->access->canViewShopAttention($user)) {
            return 'manager';
        }

        if ($user->hasAnyRole([ArkRole::Advisor->value, ArkRole::Admin->value])
            && $this->access->canAccessShopCommunications($user)) {
            return 'advisor';
        }

        if ($user->hasRole(ArkRole::Technician->value)) {
            return 'technician';
        }

        return 'staff';
    }

    /**
     * RO workspace profile — matches shell operational posture, not raw role order.
     *
     * Multi-role operators (admin + advisor + technician) must not land in technician
     * inspection flow when their shell is manager/advisor.
     */
    public function repairOrderWorkspaceProfile(User $user): string
    {
        return match ($this->operationalProfile($user)) {
            'manager' => 'advisor',
            default => $this->operationalProfile($user),
        };
    }

    private function homeProfile(User $user): string
    {
        return $this->operationalProfile($user);
    }

    private function homeQuestion(User $user): string
    {
        return match ($this->homeProfile($user)) {
            'technician' => 'What do I inspect or repair next?',
            'advisor' => 'Who needs a response or decision?',
            'manager' => 'What needs attention across the shop?',
            default => 'What is next?',
        };
    }

    /**
     * @return array<string, bool>
     */
    private function capabilities(User $user): array
    {
        $shopCommunications = $this->access->canAccessShopCommunications($user);

        return [
            'mobile' => $this->access->canUseMobile($user),
            'repair_orders' => $user->can(ArkCapability::RepairOrdersView->value),
            'findings' => $user->can(ArkCapability::RepairOrdersLifecycle->value)
                || $user->can(ArkCapability::RepairOrdersManage->value),
            // Shop inbox tab — advisors/managers only; technicians use RO workspace comms.
            'conversations' => $shopCommunications,
            'communications' => $shopCommunications,
            'customer_reply' => $this->access->canReplyToCustomer($user),
            'internal_notes' => $this->access->canRecordInternalNote($user),
            'intake' => $this->access->canPerformIntake($user),
            'attention' => $this->access->canViewShopAttention($user),
            'owner_bookend' => $this->access->canViewOwnerBookend($user),
            'owner_operational_report' => $this->access->canViewOwnerOperationalReport($user),
        ];
    }

    /**
     * Role-aware bottom navigation — Flutter renders tabs from this list only.
     *
     * @return list<array{key: string, label: string, enabled: bool}>
     */
    private function navigation(User $user): array
    {
        if (! $this->access->canUseMobile($user)) {
            return [];
        }

        return [
            ['key' => 'home', 'label' => 'Home', 'enabled' => true],
            ['key' => 'comms', 'label' => 'Conversations', 'enabled' => true],
            ['key' => 'customers', 'label' => 'Customers', 'enabled' => true],
            ['key' => 'schedule', 'label' => 'Schedule', 'enabled' => true],
            ['key' => 'apps', 'label' => 'Apps', 'enabled' => true],
        ];
    }
}
