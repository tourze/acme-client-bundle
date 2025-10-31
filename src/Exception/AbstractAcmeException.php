<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

/**
 * ACME 客户端抽象异常基类
 */
abstract class AbstractAcmeException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $acmeErrorType = null,
        /** @var array<string, mixed>|null */
        private readonly ?array $acmeErrorDetails = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getAcmeErrorType(): ?string
    {
        return $this->acmeErrorType;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAcmeErrorDetails(): ?array
    {
        return $this->acmeErrorDetails;
    }
}
