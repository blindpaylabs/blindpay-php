<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

/** @internal */
final class Path
{
    /**
     * Fill each `%s` in $template with a percent-encoded path segment, so an ID can never change
     * the request path: Path::of('payouts/%s', 'a/b') is 'payouts/a%2Fb'.
     */
    public static function of(string $template, string ...$segments): string
    {
        return vsprintf($template, array_map('rawurlencode', $segments));
    }
}
