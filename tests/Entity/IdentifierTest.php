<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;

/**
 * Identifier 实体测试类
 */
class IdentifierTest extends TestCase
{
    private Identifier $identifier;

    protected function setUp(): void
    {
        $this->identifier = new Identifier();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->identifier->getId());
        $this->assertNull($this->identifier->getOrder());
        $this->assertSame('dns', $this->identifier->getType());
        $this->assertFalse($this->identifier->isWildcard());
        $this->assertFalse($this->identifier->isValid());
    }

    public function test_type_getterSetter(): void
    {
        $type = 'http';
        $result = $this->identifier->setType($type);

        $this->assertSame($this->identifier, $result);
        $this->assertSame($type, $this->identifier->getType());
    }

    public function test_type_defaultDns(): void
    {
        $this->assertSame('dns', $this->identifier->getType());
    }

    public function test_value_getterSetter(): void
    {
        $value = 'example.com';
        $result = $this->identifier->setValue($value);

        $this->assertSame($this->identifier, $result);
        $this->assertSame($value, $this->identifier->getValue());
    }

    public function test_value_withDifferentDomains(): void
    {
        $domains = [
            'example.com',
            'sub.example.com',
            'api.test.example.org',
            'localhost'
        ];

        foreach ($domains as $domain) {
            $this->identifier->setValue($domain);
            $this->assertSame($domain, $this->identifier->getValue());
        }
    }

    public function test_wildcard_getterSetter(): void
    {
        $this->assertFalse($this->identifier->isWildcard());

        $result = $this->identifier->setWildcard(true);
        $this->assertSame($this->identifier, $result);
        $this->assertTrue($this->identifier->isWildcard());

        $this->identifier->setWildcard(false);
        $this->assertFalse($this->identifier->isWildcard());
    }

    public function test_valid_getterSetter(): void
    {
        $this->assertFalse($this->identifier->isValid());

        $result = $this->identifier->setValid(true);
        $this->assertSame($this->identifier, $result);
        $this->assertTrue($this->identifier->isValid());

        $this->identifier->setValid(false);
        $this->assertFalse($this->identifier->isValid());
    }

    public function test_order_getterSetter(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);

        $result = $this->identifier->setOrder($order);
        $this->assertSame($this->identifier, $result);
        $this->assertSame($order, $this->identifier->getOrder());
    }

    public function test_order_setToNull(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);
        $this->identifier->setOrder($order);

        $this->identifier->setOrder(null);
        $this->assertNull($this->identifier->getOrder());
    }

    public function test_toString_withDnsType(): void
    {
        $this->identifier->setType('dns')->setValue('example.com');

        $this->assertSame('dns: example.com', (string) $this->identifier);
    }

    public function test_toString_withHttpType(): void
    {
        $this->identifier->setType('http')->setValue('example.com');

        $this->assertSame('http: example.com', (string) $this->identifier);
    }

    public function test_toString_withWildcardDomain(): void
    {
        $this->identifier
            ->setType('dns')
            ->setValue('*.example.com')
            ->setWildcard(true);

        $this->assertSame('dns: *.example.com', (string) $this->identifier);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->identifier);
    }

    public function test_fluentInterface_chaining(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);

        $result = $this->identifier
            ->setOrder($order)
            ->setType('http')
            ->setValue('api.example.com')
            ->setWildcard(true)
            ->setValid(true);

        $this->assertSame($this->identifier, $result);
        $this->assertSame($order, $this->identifier->getOrder());
        $this->assertSame('http', $this->identifier->getType());
        $this->assertSame('api.example.com', $this->identifier->getValue());
        $this->assertTrue($this->identifier->isWildcard());
        $this->assertTrue($this->identifier->isValid());
    }

    public function test_businessScenario_dnsIdentifier(): void
    {
        /** @var Order $order */
        $order = $this->createMock(Order::class);

        $this->identifier
            ->setOrder($order)
            ->setType('dns')
            ->setValue('app.example.com')
            ->setWildcard(false)
            ->setValid(true);

        $this->assertSame('dns: app.example.com', (string) $this->identifier);
        $this->assertFalse($this->identifier->isWildcard());
        $this->assertTrue($this->identifier->isValid());
    }

    public function test_businessScenario_wildcardDomain(): void
    {
        $this->identifier
            ->setType('dns')
            ->setValue('*.example.com')
            ->setWildcard(true)
            ->setValid(false);

        $this->assertTrue($this->identifier->isWildcard());
        $this->assertStringContainsString('*', $this->identifier->getValue());
        $this->assertFalse($this->identifier->isValid());
    }

    public function test_businessScenario_httpChallenge(): void
    {
        $this->identifier
            ->setType('http')
            ->setValue('example.com')
            ->setWildcard(false)
            ->setValid(true);

        $this->assertSame('http', $this->identifier->getType());
        $this->assertFalse($this->identifier->isWildcard());
        $this->assertTrue($this->identifier->isValid());
    }

    public function test_edgeCases_emptyValue(): void
    {
        $this->identifier->setValue('');
        $this->assertSame('', $this->identifier->getValue());
        $this->assertSame('dns: ', (string) $this->identifier);
    }

    public function test_edgeCases_longDomain(): void
    {
        $longDomain = str_repeat('a', 250) . '.com';
        $this->identifier->setValue($longDomain);

        $this->assertSame($longDomain, $this->identifier->getValue());
        $this->assertStringContainsString($longDomain, (string) $this->identifier);
    }

    public function test_edgeCases_specialCharactersInType(): void
    {
        $this->identifier->setType('custom-type');
        $this->assertSame('custom-type', $this->identifier->getType());
    }

    public function test_stateTransitions_validationFlow(): void
    {
        // 初始状态：无效
        $this->assertFalse($this->identifier->isValid());

        // 设置域名信息
        $this->identifier->setValue('example.com');
        $this->assertFalse($this->identifier->isValid()); // 仍然无效直到验证

        // 验证通过
        $this->identifier->setValid(true);
        $this->assertTrue($this->identifier->isValid());

        // 可以重新标记为无效
        $this->identifier->setValid(false);
        $this->assertFalse($this->identifier->isValid());
    }

    public function test_stateTransitions_wildcardFlow(): void
    {
        // 普通域名
        $this->identifier->setValue('example.com');
        $this->assertFalse($this->identifier->isWildcard());

        // 变更为通配符域名
        $this->identifier->setValue('*.example.com')->setWildcard(true);
        $this->assertTrue($this->identifier->isWildcard());
        $this->assertStringContainsString('*', $this->identifier->getValue());

        // 变回普通域名
        $this->identifier->setValue('app.example.com')->setWildcard(false);
        $this->assertFalse($this->identifier->isWildcard());
        $this->assertStringNotContainsString('*', $this->identifier->getValue());
    }
}
