<?php

use App\Ark\Operations\WorkTemplates\HistoricalDrivetrainKey;
use App\Ark\Vehicles\Canonical\CanonicalDrivetrain;
use App\Ark\Vehicles\DrivetrainNormalizer;

test('historical recall preserves generic 2wd and never maps it to fwd', function (string $raw) {
    expect(HistoricalDrivetrainKey::fromRaw($raw))->toBe(HistoricalDrivetrainKey::TWO)
        ->and(HistoricalDrivetrainKey::fromRaw($raw))->not->toBe(HistoricalDrivetrainKey::FWD);
})->with([
    '2WD',
    '2wd',
    '4x2',
    '4X2',
]);

test('shared drivetrain normalizer still maps 2wd to fwd for non-recall callers', function () {
    // Documented dependency: import / VehicleNormalizer still invent FWD from 2WD.
    // Historical Recall must not consume this path.
    expect((new DrivetrainNormalizer)->normalize('2WD'))->toBe(CanonicalDrivetrain::Fwd)
        ->and((new DrivetrainNormalizer)->normalize('4x2'))->toBe(CanonicalDrivetrain::Fwd);
});

test('historical recall distinguishes known drivetrains without collapsing 2wd', function (string $raw, string $expected) {
    expect(HistoricalDrivetrainKey::fromRaw($raw))->toBe($expected);
})->with([
    ['FWD', HistoricalDrivetrainKey::FWD],
    ['Front-Wheel Drive', HistoricalDrivetrainKey::FWD],
    ['RWD', HistoricalDrivetrainKey::RWD],
    ['Rear Wheel Drive', HistoricalDrivetrainKey::RWD],
    ['AWD', HistoricalDrivetrainKey::AWD],
    ['4WD', HistoricalDrivetrainKey::FOUR],
    ['4x4', HistoricalDrivetrainKey::FOUR],
]);

test('historical drivetrain comparison treats ambiguity and unknowns honestly', function () {
    expect(HistoricalDrivetrainKey::compare(HistoricalDrivetrainKey::FOUR, HistoricalDrivetrainKey::FOUR))->toBe('same')
        ->and(HistoricalDrivetrainKey::compare(HistoricalDrivetrainKey::RWD, HistoricalDrivetrainKey::FOUR))->toBe('different')
        ->and(HistoricalDrivetrainKey::compare(HistoricalDrivetrainKey::TWO, HistoricalDrivetrainKey::FOUR))->toBe('different')
        ->and(HistoricalDrivetrainKey::compare(HistoricalDrivetrainKey::TWO, HistoricalDrivetrainKey::RWD))->toBe('different')
        ->and(HistoricalDrivetrainKey::compare(HistoricalDrivetrainKey::TWO, HistoricalDrivetrainKey::FWD))->toBe('different')
        ->and(HistoricalDrivetrainKey::compare(null, HistoricalDrivetrainKey::FOUR))->toBe('unknown')
        ->and(HistoricalDrivetrainKey::compare(HistoricalDrivetrainKey::TWO, HistoricalDrivetrainKey::TWO))->toBe('same');
});
