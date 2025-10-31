<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Identifier 实体测试类
 *
 * @internal
 */
#[CoversClass(Identifier::class)]
final class IdentifierTest extends AbstractEntityTestCase
{
    protected function createEntity(): Identifier
    {
        return new Identifier();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'type' => ['type', 'dns'];
        yield 'value' => ['value', 'example.com'];
        yield 'wildcard' => ['wildcard', true];
        yield 'valid' => ['valid', true];
    }

    public function testToStringWithDnsType(): void
    {
        $identifier = $this->createEntity();
        $identifier->setType('dns');
        $identifier->setValue('example.com');

        $this->assertSame('dns: example.com', (string) $identifier);
    }

    public function testToStringWithHttpType(): void
    {
        $identifier = $this->createEntity();
        $identifier->setType('http');
        $identifier->setValue('example.com');

        $this->assertSame('http: example.com', (string) $identifier);
    }

    public function testToStringWithWildcardDomain(): void
    {
        $identifier = $this->createEntity();
        $identifier->setType('dns');
        $identifier->setValue('*.example.com');
        $identifier->setWildcard(true);

        $this->assertSame('dns: *.example.com', (string) $identifier);
    }

    public function testStringableInterface(): void
    {
        $identifier = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $identifier);
    }

    public function testFluentInterfaceChaining(): void
    {
        $identifier = $this->createEntity();
        $order = $this->createMock(Order::class);

        $identifier->setOrder($order);
        $identifier->setType('http');
        $identifier->setValue('api.example.com');
        $identifier->setWildcard(true);
        $identifier->setValid(true);
        $result = $identifier;

        $this->assertSame($identifier, $result);
        $this->assertSame($order, $identifier->getOrder());
        $this->assertSame('http', $identifier->getType());
        $this->assertSame('api.example.com', $identifier->getValue());
        $this->assertTrue($identifier->isWildcard());
        $this->assertTrue($identifier->isValid());
    }

    public function testBusinessScenarioDnsIdentifier(): void
    {
        $identifier = $this->createEntity();
        $order = $this->createMock(Order::class);

        $identifier->setOrder($order);
        $identifier->setType('dns');
        $identifier->setValue('app.example.com');
        $identifier->setWildcard(false);
        $identifier->setValid(true);

        $this->assertSame('dns: app.example.com', (string) $identifier);
        $this->assertFalse($identifier->isWildcard());
        $this->assertTrue($identifier->isValid());
    }

    public function testBusinessScenarioWildcardDomain(): void
    {
        $identifier = $this->createEntity();
        $identifier->setType('dns');
        $identifier->setValue('*.example.com');
        $identifier->setWildcard(true);
        $identifier->setValid(false);

        $this->assertTrue($identifier->isWildcard());
        $this->assertStringContainsString('*', $identifier->getValue());
        $this->assertFalse($identifier->isValid());
    }

    public function testBusinessScenarioHttpChallenge(): void
    {
        $identifier = $this->createEntity();
        $identifier->setType('http');
        $identifier->setValue('example.com');
        $identifier->setWildcard(false);
        $identifier->setValid(true);

        $this->assertSame('http', $identifier->getType());
        $this->assertFalse($identifier->isWildcard());
        $this->assertTrue($identifier->isValid());
    }

    public function testEdgeCasesEmptyValue(): void
    {
        $identifier = $this->createEntity();
        $identifier->setValue('');
        $this->assertSame('', $identifier->getValue());
        $this->assertSame('dns: ', (string) $identifier);
    }

    public function testEdgeCasesLongDomain(): void
    {
        $identifier = $this->createEntity();
        $longDomain = str_repeat('a', 250) . '.com';
        $identifier->setValue($longDomain);

        $this->assertSame($longDomain, $identifier->getValue());
        $this->assertStringContainsString($longDomain, (string) $identifier);
    }

    public function testEdgeCasesSpecialCharactersInType(): void
    {
        $identifier = $this->createEntity();
        $identifier->setType('custom-type');
        $this->assertSame('custom-type', $identifier->getType());
    }

    public function testStateTransitionsValidationFlow(): void
    {
        $identifier = $this->createEntity();

        // 初始状态：无效
        $this->assertFalse($identifier->isValid());

        // 设置域名信息
        $identifier->setValue('example.com');
        $this->assertFalse($identifier->isValid()); // 仍然无效直到验证

        // 验证通过
        $identifier->setValid(true);
        $this->assertTrue($identifier->isValid());

        // 可以重新标记为无效
        $identifier->setValid(false);
        $this->assertFalse($identifier->isValid());
    }

    public function testStateTransitionsWildcardFlow(): void
    {
        $identifier = $this->createEntity();

        // 普通域名
        $identifier->setValue('example.com');
        $this->assertFalse($identifier->isWildcard());

        // 变更为通配符域名
        $identifier->setValue('*.example.com');
        $identifier->setWildcard(true);
        $this->assertTrue($identifier->isWildcard());
        $this->assertStringContainsString('*', $identifier->getValue());

        // 变回普通域名
        $identifier->setValue('app.example.com');
        $identifier->setWildcard(false);
        $this->assertFalse($identifier->isWildcard());
        $this->assertStringNotContainsString('*', $identifier->getValue());
    }
}
