<?php

namespace App\Ark\Operations\Financial;

enum OverpaymentDisposition: string
{
    case Ask = 'ask';
    case KeepCredit = 'keep_credit';
    case ReturnChange = 'return_change';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Ask => 'Ask every time',
            self::KeepCredit => 'Keep as account credit',
            self::ReturnChange => 'Return excess when possible',
            self::Refund => 'Pending refund / return',
        };
    }

    /**
     * @return list<self>
     */
    public static function customerDefaults(): array
    {
        return [self::Ask, self::KeepCredit, self::ReturnChange];
    }

    /**
     * @return list<self>
     */
    public static function paymentChoices(PaymentMethod|string $method): array
    {
        if (PaymentMethod::isCash($method)) {
            return [self::KeepCredit, self::ReturnChange];
        }

        return [self::KeepCredit, self::Refund];
    }
}
