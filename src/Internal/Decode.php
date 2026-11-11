<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use BackedEnum;

/**
 * Forward-compatible decoding helpers for response DTOs.
 *
 * @internal
 */
final class Decode
{
    /**
     * Decode a wire value into a string-backed enum without failing on values this SDK version
     * does not know yet: a known value becomes its enum case, an unknown one is returned as the
     * raw string, and null stays null. Response properties decoded this way are typed
     * `SomeEnum|string`. A value of the wrong JSON type is kept as its JSON text, never thrown on.
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum  a string-backed enum
     * @return T|string|null
     */
    public static function enum(string $enum, mixed $value): BackedEnum|string|null
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return (string) json_encode($value);
        }

        return $enum::tryFrom($value) ?? $value;
    }

    /**
     * The wire value of a property decoded with enum(): the case's backing value, or the raw
     * string kept for an unknown value.
     */
    public static function wire(BackedEnum|string|null $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }
}
