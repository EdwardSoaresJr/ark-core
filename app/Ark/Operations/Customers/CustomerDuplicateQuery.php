<?php

namespace App\Ark\Operations\Customers;

use Illuminate\Support\Collection;

class CustomerDuplicateQuery
{
    /**
     * @return Collection<int, array{customer: Customer, reasons: list<string>}>
     */
    public static function potentialDuplicates(
        string $firstName = '',
        string $lastName = '',
        string $phone = '',
        string $email = '',
        int $limit = 6,
        ?int $excludeCustomerId = null,
    ): Collection {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $phone = trim($phone);
        $email = strtolower(trim($email));

        /** @var array<int, array{customer: Customer, reasons: list<string>}> $matches */
        $matches = [];

        $remember = function (Customer $customer, string $reason) use (&$matches): void {
            $id = $customer->id;

            if (! isset($matches[$id])) {
                $matches[$id] = [
                    'customer' => $customer,
                    'reasons' => [],
                ];
            }

            if (! in_array($reason, $matches[$id]['reasons'], true)) {
                $matches[$id]['reasons'][] = $reason;
            }
        };

        if (str_contains($email, '@') && strlen($email) >= 5) {
            Customer::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->limit($limit)
                ->get()
                ->each(fn (Customer $customer) => $remember($customer, 'Email'));
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($phoneDigits) >= 7) {
            $needle = strlen($phoneDigits) > 10
                ? substr($phoneDigits, -10)
                : $phoneDigits;

            Customer::query()
                ->whereNotNull('phone')
                ->where('phone', 'like', '%'.$needle.'%')
                ->limit($limit)
                ->get()
                ->each(fn (Customer $customer) => $remember($customer, 'Phone'));
        }

        if (strlen($lastName) >= 2 && strlen($firstName) >= 2) {
            Customer::query()
                ->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)])
                ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                ->limit($limit)
                ->get()
                ->each(fn (Customer $customer) => $remember($customer, 'Name'));
        }

        return collect($matches)
            ->when($excludeCustomerId, fn ($matches) => $matches->reject(
                fn (array $match): bool => $match['customer']->id === $excludeCustomerId,
            ))
            ->sortByDesc(fn (array $match): int => count($match['reasons']))
            ->take($limit)
            ->values();
    }
}
