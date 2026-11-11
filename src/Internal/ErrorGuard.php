<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use ErrorException;

/** @internal */
final class ErrorGuard
{
    /**
     * Run $fn with PHP warnings and notices (e.g. an undefined array key) raised as
     * ErrorException, so decoding an unexpected body fails loudly instead of yielding nulls.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public static function run(callable $fn): mixed
    {
        set_error_handler(static function (int $severity, string $message): bool {
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
