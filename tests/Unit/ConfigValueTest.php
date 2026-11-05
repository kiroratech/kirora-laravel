<?php

declare(strict_types=1);

use Kirora\Laravel\Support\ConfigValue;

it('reads numbers from env strings', function (mixed $given, mixed $expected) {
    expect(ConfigValue::number($given))->toBe($expected);
})->with([
    'whole' => ['3', 3],
    'whole as decimal' => ['3.0', 3],
    'exponent' => ['1e1', 10],
    'decimal' => ['12.5', 12.5],
    'padded' => [' 7 ', 7],
    'unset' => [null, null],
    'empty' => ['', null],
    'not a number' => ['lots', 'lots'],
    'already a number' => [2.5, 2.5],
]);

it('reads switches from env strings', function (mixed $given, bool $expected) {
    expect(ConfigValue::boolean($given))->toBe($expected);
})->with([
    [null, false], [true, true], [false, false], ['true', true], ['false', false], ['off', false],
    ['no', false], ['0', false], ['', false], ['on', true], ['1', true], ['typo', true],
]);
