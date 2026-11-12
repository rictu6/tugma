<?php

use App\Models\Accomplishment;

it('provides the supported accomplishment types and labels', function () {
    expect(Accomplishment::typeOptions())->toBe([
        'fixed' => 'Fixed',
        'recurring_quarterly' => 'Recurring Quarterly',
        'recurring_semestral' => 'Recurring Semestral',
        'cumulative' => 'Cumulative',
    ]);
});

it('provides the default period count for each accomplishment type', function () {
    expect(Accomplishment::defaultPeriodCountForType('fixed'))->toBe(1)
        ->and(Accomplishment::defaultPeriodCountForType('recurring_quarterly'))->toBe(4)
        ->and(Accomplishment::defaultPeriodCountForType('recurring_semestral'))->toBe(2)
        ->and(Accomplishment::defaultPeriodCountForType('cumulative'))->toBe(4);
});

it('allows actual accomplishment data to be empty', function () {
    $accomplishment = new Accomplishment([
        'actual' => null,
        'actual_accomplishment_description' => null,
        'date_accomplished' => null,
    ]);

    expect($accomplishment->actual)->toBeNull()
        ->and($accomplishment->actual_accomplishment_description)->toBeNull()
        ->and($accomplishment->date_accomplished)->toBeNull();
});

it('compares numeric actuals with their targets', function (?string $actual, ?string $target, ?string $unit, ?int $expected) {
    $accomplishment = new Accomplishment(compact('actual', 'target', 'unit'));

    expect($accomplishment->actualTargetComparison())->toBe($expected);
})->with([
    ['4', '5', 'number', -1],
    ['5.00', '5', 'number', 0],
    ['6', '5', 'number', 1],
    ['0', '0', 'number', 0],
    ['49%', '50%', 'percentage', -1],
    ['50', '50%', 'percentage', 0],
    ['50%%', '50%', 'percentage', null],
    [null, '5', 'number', null],
    ['5', null, 'number', null],
    ['5', 'At least 5', 'number', null],
    ['unknown', '5', 'number', null],
    ['5%', '5', 'number', null],
]);
