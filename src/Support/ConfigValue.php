<?php

declare(strict_types=1);

namespace Kirora\Laravel\Support;

/**
 * Reads config values that come from environment variables, which are strings.
 *
 * @internal
 */
final class ConfigValue
{
    /**
     * Null for an unset value. Anything else that isn't a string is returned as it is,
     * for the SDK to say what's wrong with it.
     */
    public static function string(mixed $value): mixed
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * A numeric string as a number: an int when it's whole, such as `3` or `3.0`, so
     * options that need an int accept it. Anything else goes on unchanged.
     */
    public static function number(mixed $value): mixed
    {
        $value = self::string($value);
        if (! is_string($value) || ! is_numeric($trimmed = trim($value))) {
            return $value;
        }
        $number = $trimmed + 0;
        // INF fails the range check; is_numeric() never lets NAN through.
        if (is_float($number) && floor($number) === $number && abs($number) <= PHP_INT_MAX) {
            return (int) $number;
        }

        return $number;
    }

    /**
     * A switch: `false`, `0`, `off`, `no`, an empty string and null are off. Any other value
     * it can't read stays on, so a typo doesn't quietly turn something off.
     */
    public static function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
