<?php

use Carbon\CarbonImmutable;

it('formats cents as Dutch money', function (int $cents, string $expected) {
    expect(money($cents))->toBe($expected);
})->with([
    [0, '€ 0,00'],
    [1500, '€ 15,00'],
    [123450, '€ 1.234,50'],
    [-500, '-€ 5,00'],
]);

it('formats dates the human way', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 12:00', 'Europe/Amsterdam'));
    Carbon\Carbon::setTestNow(CarbonImmutable::now());

    $today = CarbonImmutable::parse('2026-11-10 14:30', 'Europe/Amsterdam');

    expect(human_date($today))->toBe('Vandaag')
        ->and(human_date($today->addDay()))->toBe('Morgen')
        ->and(human_date($today->subDay()))->toBe('Gisteren')
        ->and(human_date($today->addDays(4)))->toBe('za 14 nov')
        ->and(human_datetime($today))->toBe('Vandaag 14:30');
});

it('converts utc times to the shop timezone', function () {
    Carbon\Carbon::setTestNow(CarbonImmutable::parse('2026-11-10 12:00', 'Europe/Amsterdam'));

    expect(human_datetime(CarbonImmutable::parse('2026-11-10 13:30', 'UTC')))->toBe('Vandaag 14:30');
});
