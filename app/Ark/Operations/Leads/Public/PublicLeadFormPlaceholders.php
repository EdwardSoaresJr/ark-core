<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Rotating demo placeholders for the public lead form (and matching sign-in hint).
 * Decorative only — never treated as customer truth.
 */
final class PublicLeadFormPlaceholders
{
    /**
     * @return list<array{first_name: string, last_name: string, phone: string, email: string, sign_in: string}>
     */
    public static function sets(): array
    {
        return [
            [
                'first_name' => 'Tommy',
                'last_name' => 'Tutone',
                'phone' => '555-867-5309',
                'email' => 'jenny@example.com',
                'sign_in' => 'jenny@example.com or 555-867-5309',
            ],
            [
                'first_name' => 'Dade',
                'last_name' => 'Murphy',
                'phone' => '555-555-4202',
                'email' => 'crashoverride@example.com',
                'sign_in' => 'crashoverride@example.com or 555-555-4202',
            ],
            [
                'first_name' => 'Kate',
                'last_name' => 'Libby',
                'phone' => '555-555-4202',
                'email' => 'acidburn@example.com',
                'sign_in' => 'acidburn@example.com or 555-555-4202',
            ],
            [
                'first_name' => 'Peter',
                'last_name' => 'Venkman',
                'phone' => '555-555-2368',
                'email' => 'venkman@example.com',
                'sign_in' => 'venkman@example.com or 555-555-2368',
            ],
            [
                'first_name' => 'Ferris',
                'last_name' => 'Bueller',
                'phone' => '555-555-2383',
                'email' => 'ferris@example.com',
                'sign_in' => 'ferris@example.com or 555-555-2383',
            ],
            [
                'first_name' => 'Thomas',
                'last_name' => 'Anderson',
                'phone' => '555-555-0690',
                'email' => 'neo@example.com',
                'sign_in' => 'neo@example.com or 555-555-0690',
            ],
        ];
    }

    /**
     * @return array{first_name: string, last_name: string, phone: string, email: string, sign_in: string}
     */
    public static function random(): array
    {
        $sets = self::sets();

        return $sets[array_rand($sets)];
    }
}
