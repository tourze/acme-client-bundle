<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Exception;

class QueryHandlerNotFoundException extends AbstractAcmeException
{
    /**
     * @param array<string, mixed> $options
     */
    public static function forOptions(array $options): self
    {
        return new self('No suitable handler found for the given options: ' . json_encode($options));
    }
}
