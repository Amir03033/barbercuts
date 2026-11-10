<?php

use App\Support\Color;
use App\Support\WeekSchedule;

it('parses money input to cents', function (?string $input, ?int $expected) {
    expect(parse_money($input))->toBe($expected);
})->with([
    ['25', 2500],
    ['25,5', 2550],
    ['25,50', 2550],
    ['25.50', 2550],
    ['€ 1.234,50', 123450],
    ['0', 0],
    ['', null],
    [null, null],
    ['-5', null],
    ['gratis', null],
    ['12,345', null],
]);

it('formats cents for an input field', function () {
    expect(money_input(2500))->toBe('25,00')->and(money_input(null))->toBe('');
});

it('labels durations', function () {
    expect(duration_label(30))->toBe('30 min')
        ->and(duration_label(90))->toBe('1 u 30 min')
        ->and(duration_label(1440))->toBe('24 uur');
});

it('picks white or black text for an accent colour', function (string $accent, string $expected) {
    expect(Color::onColor($accent))->toBe($expected)
        ->and(Color::contrast($accent, $expected))->toBeGreaterThanOrEqual(4.5);
})->with([
    ['#111827', '#ffffff'],
    ['#2563eb', '#ffffff'],
    ['#facc15', '#111114'],
    ['#ffffff', '#111114'],
    ['#dc2626', '#ffffff'],
    ['#16a34a', '#111114'],
]);

it('gives readable text on every preset colour', function () {
    foreach ([...Color::PRESETS, ...Color::STAFF_COLORS] as $color) {
        expect(Color::contrast($color, Color::onColor($color)))->toBeGreaterThanOrEqual(4.5, $color);
    }
});

it('calculates wcag contrast', function () {
    expect(round(Color::contrast('#000000', '#ffffff'), 1))->toBe(21.0)
        ->and(Color::contrast('#777777', '#777777'))->toBe(1.0);
});

it('uses tuesday to saturday 09:00–18:00 as preset', function () {
    $week = WeekSchedule::preset();

    expect(array_keys(array_filter($week, fn (array $day) => $day['open'])))->toBe([2, 3, 4, 5, 6])
        ->and($week[2]['blocks'])->toBe([['from' => '09:00', 'to' => '18:00']])
        ->and(WeekSchedule::errors($week))->toBe([]);
});

it('finds errors in a week schedule', function (array $blocks, ?string $expected) {
    $week = WeekSchedule::preset();
    $week[2]['blocks'] = $blocks;

    expect(WeekSchedule::errors($week)[2] ?? null)->toBe($expected);
})->with([
    'valid with break' => [[['from' => '09:00', 'to' => '12:30'], ['from' => '13:00', 'to' => '18:00']], null],
    'touching blocks' => [[['from' => '09:00', 'to' => '13:00'], ['from' => '13:00', 'to' => '18:00']], null],
    'end before start' => [[['from' => '18:00', 'to' => '09:00']], 'De eindtijd moet na de begintijd liggen.'],
    'overlap' => [[['from' => '09:00', 'to' => '13:00'], ['from' => '12:00', 'to' => '18:00']], 'De blokken overlappen elkaar.'],
    'empty time' => [[['from' => '', 'to' => '18:00']], 'Vul een begin- en eindtijd in.'],
    'not a time' => [[['from' => '25:00', 'to' => '26:00']], 'Vul een begin- en eindtijd in.'],
    'no blocks' => [[], 'Vul een begin- en eindtijd in.'],
]);

it('ignores blocks of closed days', function () {
    $week = WeekSchedule::preset();
    $week[1]['blocks'] = [['from' => 'x', 'to' => 'y']];

    expect(WeekSchedule::errors($week))->toBe([])
        ->and(collect(WeekSchedule::toRows($week, 'opens_at', 'closes_at'))->pluck('day_of_week')->all())->toBe([2, 3, 4, 5, 6]);
});

it('round-trips between rows and form state', function () {
    $week = WeekSchedule::preset();
    $week[3]['blocks'] = [['from' => '09:00', 'to' => '12:30'], ['from' => '13:00', 'to' => '18:00']];

    $rows = collect(WeekSchedule::toRows($week, 'starts_at', 'ends_at'))->map(fn (array $row) => (object) $row);

    expect(WeekSchedule::fromRows($rows, 'starts_at', 'ends_at'))->toBe($week);
});
