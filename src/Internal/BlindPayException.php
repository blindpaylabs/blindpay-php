<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use Exception;

/**
 * Base exception for BlindPay SDK errors.
 *
 * Every exception the SDK throws is a \BlindPay\SDK\BlindPayException, a subclass of this one: catch
 * that public class in new code. This class stays for existing `catch` blocks.
 */
class BlindPayException extends Exception
{
    /**
     * Error code for the exception
     */
    private ?string $errorCode;

    /**
     * Additional context data for the error
     */
    private array $context;

    public function __construct(
        string $message,
        ?string $errorCode = null,
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /*
     * The factories use late static binding, so BlindPay\SDK\BlindPayException::configuration()
     * returns the public class. Only call them on classes that keep this constructor:
     * BlindPayApiException has its own (it takes an ErrorResponse), hence the PHPStan ignores.
     */
    public static function validation(string $message, array $context = []): static
    {
        // @phpstan-ignore new.static
        return new static(message: $message, errorCode: 'VALIDATION_ERROR', context: $context);
    }

    public static function api(string $message, ?string $errorCode = null, array $context = []): static
    {
        // @phpstan-ignore new.static
        return new static(message: $message, errorCode: $errorCode ?? 'API_ERROR', context: $context);
    }

    public static function configuration(string $message): static
    {
        // @phpstan-ignore new.static
        return new static(message: $message, errorCode: 'CONFIGURATION_ERROR');
    }

    public static function authentication(string $message = 'Authentication failed'): static
    {
        // @phpstan-ignore new.static
        return new static(message: $message, errorCode: 'AUTHENTICATION_ERROR', code: 401);
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
        ];
    }
}
