<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

/**
 * ACME 速率限制异常
 */
class AcmeRateLimitException extends AcmeClientException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?\Throwable $previous = null,
        ?string $acmeErrorType = 'rateLimited',
        ?array $acmeErrorDetails = null,
        private readonly ?\DateTimeInterface $retryAfter = null
    ) {
        parent::__construct($message, $code, $previous, $acmeErrorType, $acmeErrorDetails);
    }

    public function getRetryAfter(): ?\DateTimeInterface
    {
        return $this->retryAfter;
    }
}
