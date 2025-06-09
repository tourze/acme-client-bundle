<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

/**
 * ACME 服务器异常
 */
class AcmeServerException extends AcmeClientException
{
    public function __construct(
        string $message = 'ACME server error',
        int $code = 500,
        ?\Throwable $previous = null,
        ?string $acmeErrorType = 'serverInternal',
        ?array $acmeErrorDetails = null
    ) {
        parent::__construct($message, $code, $previous, $acmeErrorType, $acmeErrorDetails);
    }
}
