<?php

namespace App\Ark\Runtime\Authorization;

enum ArkRole: string
{
    case Admin = 'admin';
    case Advisor = 'advisor';
    case Technician = 'technician';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Advisor => 'Advisor',
            self::Technician => 'Technician',
            self::Customer => 'Customer',
        };
    }

    public function chipClass(): string
    {
        return match ($this) {
            self::Admin => 'ops-role-chip--admin',
            self::Advisor => 'ops-role-chip--advisor',
            self::Technician => 'ops-role-chip--technician',
            self::Customer => 'ops-role-chip--customer',
        };
    }

    /**
     * @return list<self>
     */
    public static function staffAssignable(): array
    {
        return [
            self::Admin,
            self::Advisor,
            self::Technician,
        ];
    }
}
