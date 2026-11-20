<?php

use App\Ark\Operations\Financial\AccountsReceivableInvoiceLabel;

test('AR invoice labels lead with the repair order number advisors recognize', function () {
    expect(AccountsReceivableInvoiceLabel::forRepairOrderNumber(1042))
        ->toBe('RO #1042 · Invoice')
        ->and(AccountsReceivableInvoiceLabel::forRepairOrderNumber(null))
        ->toBe('Invoice')
        ->and(AccountsReceivableInvoiceLabel::forRepairOrderNumber(''))
        ->toBe('Invoice');
});
