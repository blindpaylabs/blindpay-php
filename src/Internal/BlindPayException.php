<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use Exception;

/*
 * Base exception for BlindPay SDK errors
 */
class BlindPayException extends Exception
{
    /*
     * Error code for the exception
     */
    private ?string $errorCode;

    /*
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

    public static function validation(string $message, array $context = []): self
    {
        return new self(
            message: $message,
            errorCode: 'VALIDATION_ERROR',
            context: $context
        );
    }

    public static function api(string $message, ?string $errorCode = null, array $context = []): self
    {
        return new self(
            message: $message,
            errorCode: $errorCode ?? 'API_ERROR',
            context: $context
        );
    }

    public static function configuration(string $message): self
    {
        return new self(
            message: $message,
            errorCode: 'CONFIGURATION_ERROR'
        );
    }

    public static function authentication(string $message = 'Authentication failed'): self
    {
        return new self(
            message: $message,
            errorCode: 'AUTHENTICATION_ERROR',
            code: 401
        );
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
