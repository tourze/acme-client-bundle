<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

/**
 * ACME 验证异常
 */
class AcmeValidationException extends AcmeClientException
{
    public function __construct(
        string $message = 'ACME validation failed',
        int $code = 400,
        ?\Throwable $previous = null,
        ?string $acmeErrorType = 'malformed',
        ?array $acmeErrorDetails = null
    ) {
        parent::__construct($message, $code, $previous, $acmeErrorType, $acmeErrorDetails);
    }
}
