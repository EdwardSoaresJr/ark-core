<?php

use App\Ark\Operations\Leads\LeadContactNameParser;

test('a missing last name is stored as a hyphen and still recognized from older records', function () {
    expect(LeadContactNameParser::normalizeLastName(''))->toBe('-')
        ->and(LeadContactNameParser::isPlaceholderLastName('-'))->toBeTrue()
        ->and(LeadContactNameParser::isPlaceholderLastName("\u{2014}"))->toBeTrue()
        ->and(LeadContactNameParser::formatFullName('Ada', "\u{2014}"))->toBe('Ada')
        ->and(LeadContactNameParser::formatFullName('Ada', '-'))->toBe('Ada');
});
