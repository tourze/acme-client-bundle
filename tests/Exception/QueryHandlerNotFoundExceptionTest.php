<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\QueryHandlerNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(QueryHandlerNotFoundException::class)]
final class QueryHandlerNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionExtendsAbstractAcmeException(): void
    {
        $exception = new QueryHandlerNotFoundException('Test message');

        $this->assertInstanceOf(AbstractAcmeException::class, $exception);
    }

    public function testForOptionsCreatesExceptionWithFormattedMessage(): void
    {
        $options = [
            'type' => 'unknown',
            'operation' => 'test',
            'cleanup' => null,
        ];

        $exception = QueryHandlerNotFoundException::forOptions($options);

        $this->assertInstanceOf(QueryHandlerNotFoundException::class, $exception);
        $this->assertStringContainsString('No suitable handler found for the given options:', $exception->getMessage());
        $jsonOptions = json_encode($options);
        $this->assertIsString($jsonOptions);
        $this->assertStringContainsString($jsonOptions, $exception->getMessage());
    }

    public function testForOptionsWithEmptyArray(): void
    {
        $options = [];

        $exception = QueryHandlerNotFoundException::forOptions($options);

        $this->assertInstanceOf(QueryHandlerNotFoundException::class, $exception);
        $this->assertStringContainsString('No suitable handler found for the given options: []', $exception->getMessage());
    }

    public function testForOptionsWithComplexData(): void
    {
        $options = [
            'type' => 'operation',
            'nested' => ['key' => 'value'],
            'boolean' => true,
            'number' => 42,
        ];

        $exception = QueryHandlerNotFoundException::forOptions($options);

        $expectedJson = json_encode($options);
        $this->assertIsString($expectedJson);
        $this->assertStringContainsString($expectedJson, $exception->getMessage());
    }
}
