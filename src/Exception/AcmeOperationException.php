<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

/**
 * ACME 操作异常
 *
 * 用于一般性的ACME操作错误，不属于特定分类的异常
 */
class AcmeOperationException extends AbstractAcmeException
{
    /**
     * @param array<string, mixed>|null $acmeErrorDetails
     */
    public function __construct(
        string $message = 'ACME operation failed',
        int $code = 500,
        ?\Throwable $previous = null,
        ?string $acmeErrorType = 'operationFailed',
        ?array $acmeErrorDetails = null,
    ) {
        parent::__construct($message, $code, $previous, $acmeErrorType, $acmeErrorDetails);
    }
}
