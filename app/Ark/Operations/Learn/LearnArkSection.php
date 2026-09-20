<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use App\Support\Branding\Branding;

final class LearnArkSection
{
    public const OWNER = 'owner';

    /** @var list<ArkRole> Highest staff role first — used for "role and down" learn access. */
    private const STAFF_ROLE_HIERARCHY = [
        ArkRole::Admin,
        ArkRole::Advisor,
        ArkRole::Technician,
    ];

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $chipClass,
    ) {}

    public static function fromKey(string $key): ?self
    {
        return match ($key) {
            ArkRole::Admin->value => self::admin(),
            ArkRole::Advisor->value => self::advisor(),
            ArkRole::Technician->value => self::technician(),
            self::OWNER => self::owner(),
            default => null,
        };
    }

    public static function admin(): self
    {
        return new self(ArkRole::Admin->value, 'Admin', 'ops-role-chip--admin');
    }

    public static function advisor(): self
    {
        return new self(ArkRole::Advisor->value, 'Advisor', 'ops-role-chip--advisor');
    }

    public static function technician(): self
    {
        return new self(ArkRole::Technician->value, 'Technician', 'ops-role-chip--technician');
    }

    public static function owner(): self
    {
        return new self(self::OWNER, 'Owner', 'ops-role-chip--admin');
    }

    /**
     * @return list<self>
     */
    public static function visibleFor(User $user): array
    {
        $highestRole = self::highestStaffRole($user);

        if ($highestRole === null) {
            return [];
        }

        $sections = [];

        if ($highestRole === ArkRole::Admin) {
            $sections[] = self::owner();
        }

        foreach (self::STAFF_ROLE_HIERARCHY as $role) {
            if (self::hierarchyRank($role) >= self::hierarchyRank($highestRole)) {
                $sections[] = self::forStaffRole($role);
            }
        }

        return $sections;
    }

    public function isOwner(): bool
    {
        return $this->key === self::OWNER;
    }

    public function staffRole(): ?ArkRole
    {
        return ArkRole::tryFrom($this->key);
    }

    public static function canView(User $user, self $section): bool
    {
        if ($section->isOwner()) {
            return $user->hasRole(ArkRole::Admin->value);
        }

        $highestRole = self::highestStaffRole($user);
        $sectionRole = $section->staffRole();

        if ($highestRole === null || $sectionRole === null) {
            return false;
        }

        return self::hierarchyRank($sectionRole) >= self::hierarchyRank($highestRole);
    }

    public static function highestStaffRole(User $user): ?ArkRole
    {
        foreach (self::STAFF_ROLE_HIERARCHY as $role) {
            if ($user->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    private static function forStaffRole(ArkRole $role): self
    {
        return match ($role) {
            ArkRole::Admin => self::admin(),
            ArkRole::Advisor => self::advisor(),
            ArkRole::Technician => self::technician(),
            ArkRole::Customer => throw new \InvalidArgumentException('Customer is not a '.Branding::learnName().' staff section.'),
        };
    }

    private static function hierarchyRank(ArkRole $role): int
    {
        $rank = array_search($role, self::STAFF_ROLE_HIERARCHY, true);

        return $rank === false ? PHP_INT_MAX : $rank;
    }
}
