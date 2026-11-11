<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use BackedEnum;

/** @internal */
final class Query
{
    /**
     * The `?a=b&c=d` suffix for $params, or '' when nothing is set. Null and empty-string values
     * are skipped (the API never sees "null" or ""), booleans become `true`/`false`, enums their
     * wire value; insertion order is kept and names and values are RFC 3986-encoded.
     *
     * @param  array<string, scalar|BackedEnum|null>  $params
     */
    public static function build(array $params): string
    {
        $pairs = [];
        foreach ($params as $name => $value) {
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $pairs[] = rawurlencode((string) $name).'='.rawurlencode((string) $value);
        }

        return $pairs === [] ? '' : '?'.implode('&', $pairs);
    }
}
