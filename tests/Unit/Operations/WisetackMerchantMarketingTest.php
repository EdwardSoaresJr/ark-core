<?php

use App\Ark\Operations\Leads\Public\WisetackMerchantMarketing;

test('wisetack merchant marketing exposes toolkit button styling', function (): void {
    expect(WisetackMerchantMarketing::PREQUAL_BUTTON_LABEL)->toBe('Prequalify now')
        ->and(WisetackMerchantMarketing::PREQUAL_BUTTON_BACKGROUND)->toBe('#0c4e9e');
});
