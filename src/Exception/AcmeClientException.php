<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

/**
 * ACME 客户端基础异常类
 */
class AcmeClientException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $acmeErrorType = null,
        private readonly ?array $acmeErrorDetails = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getAcmeErrorType(): ?string
    {
        return $this->acmeErrorType;
    }

    public function getAcmeErrorDetails(): ?array
    {
        return $this->acmeErrorDetails;
    }
}
