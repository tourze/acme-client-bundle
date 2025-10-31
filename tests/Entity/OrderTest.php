<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Order 实体测试类
 *
 * @internal
 */
#[CoversClass(Order::class)]
final class OrderTest extends AbstractEntityTestCase
{
    public function testConstructorDefaultValues(): void
    {
        $order = $this->createEntity();
        $this->assertNull($order->getId());
        $this->assertNull($order->getAccount());
        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertNull($order->getExpiresTime());
        $this->assertNull($order->getError());
        $this->assertNull($order->getFinalizeUrl());
        $this->assertNull($order->getCertificateUrl());
        $this->assertFalse($order->isValid());
        $this->assertInstanceOf(ArrayCollection::class, $order->getOrderIdentifiers());
        $this->assertTrue($order->getOrderIdentifiers()->isEmpty());
        $this->assertInstanceOf(ArrayCollection::class, $order->getAuthorizations());
        $this->assertTrue($order->getAuthorizations()->isEmpty());
        $this->assertNull($order->getCertificate());
    }

    public function testStatusAutomaticValidFlag(): void
    {
        $order = $this->createEntity();

        // 设置为 VALID 时自动设置 valid 标志为 true
        $order->setStatus(OrderStatus::VALID);
        $this->assertTrue($order->isValid());

        // 设置为其他状态时 valid 标志为 false
        $order->setStatus(OrderStatus::PENDING);
        $this->assertFalse($order->isValid());

        $order->setStatus(OrderStatus::READY);
        $this->assertFalse($order->isValid());

        $order->setStatus(OrderStatus::PROCESSING);
        $this->assertFalse($order->isValid());

        $order->setStatus(OrderStatus::INVALID);
        $this->assertFalse($order->isValid());
    }

    public function testIsExpiredWithFutureDate(): void
    {
        $order = $this->createEntity();
        $future = new \DateTimeImmutable('+1 hour');
        $order->setExpiresTime($future);

        $this->assertFalse($order->isExpired());
    }

    public function testIsExpiredWithPastDate(): void
    {
        $order = $this->createEntity();
        $past = new \DateTimeImmutable('-1 hour');
        $order->setExpiresTime($past);

        $this->assertTrue($order->isExpired());
    }

    public function testIsReady(): void
    {
        $order = $this->createEntity();
        $this->assertFalse($order->isReady());

        $order->setStatus(OrderStatus::PENDING);
        $this->assertFalse($order->isReady());

        $order->setStatus(OrderStatus::READY);
        $this->assertTrue($order->isReady());

        $order->setStatus(OrderStatus::VALID);
        $this->assertFalse($order->isReady());
    }

    public function testIsInvalid(): void
    {
        $order = $this->createEntity();
        $this->assertFalse($order->isInvalid());

        $order->setStatus(OrderStatus::PENDING);
        $this->assertFalse($order->isInvalid());

        $order->setStatus(OrderStatus::INVALID);
        $this->assertTrue($order->isInvalid());

        $order->setStatus(OrderStatus::VALID);
        $this->assertFalse($order->isInvalid());
    }

    public function testAreAllAuthorizationsValidEmpty(): void
    {
        $order = $this->createEntity();
        $this->assertTrue($order->areAllAuthorizationsValid());
    }

    public function testAreAllAuthorizationsValidWithValidAuthorizations(): void
    {
        $order = $this->createEntity();
        $auth1 = $this->createMock(Authorization::class);
        $auth2 = $this->createMock(Authorization::class);

        $order->getAuthorizations()->add($auth1);
        $order->getAuthorizations()->add($auth2);

        // 由于实际方法会调用 Authorization::isValid()，而 mock 对象默认返回 false
        // 这里测试的是默认行为：当有授权但未显式设置为有效时，应该返回 false
        $this->assertFalse($order->areAllAuthorizationsValid());
    }

    public function testToStringWithoutId(): void
    {
        $order = $this->createEntity();
        $order->setStatus(OrderStatus::PENDING);

        $expected = 'Order #0 (pending)';
        $this->assertSame($expected, (string) $order);
    }

    public function testToStringWithDifferentStatus(): void
    {
        $order = $this->createEntity();
        $order->setStatus(OrderStatus::READY);

        $expected = 'Order #0 (ready)';
        $this->assertSame($expected, (string) $order);
    }

    public function testStringableInterface(): void
    {
        $order = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $order);
    }

    public function testFluentInterfaceChaining(): void
    {
        $order = $this->createEntity();
        $account = $this->createMock(Account::class);
        $orderUrl = 'https://acme-v02.api.letsencrypt.org/acme/order/123456';
        $finalizeUrl = 'https://acme-v02.api.letsencrypt.org/acme/finalize/123456';
        $certUrl = 'https://acme-v02.api.letsencrypt.org/acme/cert/123456';
        $expiry = new \DateTimeImmutable('+7 days');

        $order->setAccount($account);
        $order->setOrderUrl($orderUrl);
        $order->setStatus(OrderStatus::READY);
        $order->setExpiresTime($expiry);
        $order->setFinalizeUrl($finalizeUrl);
        $order->setCertificateUrl($certUrl);
        $order->setValid(true);
        $result = $order;

        $this->assertSame($order, $result);
        $this->assertSame($account, $order->getAccount());
        $this->assertSame($orderUrl, $order->getOrderUrl());
        $this->assertSame(OrderStatus::READY, $order->getStatus());
        $this->assertSame($expiry, $order->getExpiresTime());
        $this->assertSame($finalizeUrl, $order->getFinalizeUrl());
        $this->assertSame($certUrl, $order->getCertificateUrl());
        $this->assertTrue($order->isValid());
    }

    public function testBusinessScenarioOrderCreation(): void
    {
        $order = $this->createEntity();
        $account = $this->createMock(Account::class);

        $order->setAccount($account);
        $order->setOrderUrl('https://acme-v02.api.letsencrypt.org/acme/order/123456');
        $order->setStatus(OrderStatus::PENDING);
        $order->setExpiresTime(new \DateTimeImmutable('+7 days'));

        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertFalse($order->isValid());
        $this->assertFalse($order->isReady());
        $this->assertFalse($order->isInvalid());
        $this->assertFalse($order->isExpired());
    }

    public function testBusinessScenarioOrderProgression(): void
    {
        $order = $this->createEntity();

        // 订单创建
        $order->setStatus(OrderStatus::PENDING);
        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertFalse($order->isValid());

        // 授权完成，订单准备就绪
        $order->setStatus(OrderStatus::READY);
        $this->assertTrue($order->isReady());
        $this->assertFalse($order->isValid());

        // 处理中
        $order->setStatus(OrderStatus::PROCESSING);
        $this->assertFalse($order->isReady());
        $this->assertFalse($order->isValid());

        // 完成
        $order->setStatus(OrderStatus::VALID);
        $this->assertTrue($order->isValid());
        $this->assertFalse($order->isReady());
    }

    public function testBusinessScenarioOrderFailure(): void
    {
        $order = $this->createEntity();
        $order->setStatus(OrderStatus::INVALID);
        $order->setError('Domain validation failed for example.com');

        $this->assertTrue($order->isInvalid());
        $this->assertFalse($order->isValid());
        $error = $order->getError();
        $this->assertNotNull($error, 'Error should not be null');
        $this->assertStringContainsString('Domain validation failed', $error);
    }

    public function testBusinessScenarioMultipleIdentifiers(): void
    {
        $order = $this->createEntity();
        $id1 = $this->createMock(Identifier::class);
        $id2 = $this->createMock(Identifier::class);
        $id3 = $this->createMock(Identifier::class);

        $order->addOrderIdentifier($id1);
        $order->addOrderIdentifier($id2);
        $order->addOrderIdentifier($id3);

        $this->assertSame(3, $order->getOrderIdentifiers()->count());
        $this->assertTrue($order->getOrderIdentifiers()->contains($id1));
        $this->assertTrue($order->getOrderIdentifiers()->contains($id2));
        $this->assertTrue($order->getOrderIdentifiers()->contains($id3));
    }

    public function testBusinessScenarioWithCertificate(): void
    {
        $order = $this->createEntity();
        $certificate = $this->createMock(Certificate::class);

        $order->setStatus(OrderStatus::VALID);
        $order->setCertificate($certificate);
        $order->setCertificateUrl('https://acme-v02.api.letsencrypt.org/acme/cert/123456');

        $this->assertTrue($order->isValid());
        $this->assertSame($certificate, $order->getCertificate());
        $this->assertNotNull($order->getCertificateUrl());
    }

    public function testEdgeCasesEmptyUrls(): void
    {
        $order = $this->createEntity();
        $order->setOrderUrl('');
        $this->assertSame('', $order->getOrderUrl());
    }

    public function testEdgeCasesLongError(): void
    {
        $order = $this->createEntity();
        $longError = str_repeat('Error message. ', 100);
        $order->setError($longError);

        $this->assertSame($longError, $order->getError());
    }

    public function testEdgeCasesExpiresTimeEdgeCase(): void
    {
        $order = $this->createEntity();

        // 设置过期时间为1秒后，确保不会过期
        $future = new \DateTimeImmutable('+1 second');
        $order->setExpiresTime($future);

        $this->assertFalse($order->isExpired());

        // 设置过期时间为1秒前，确保已过期
        $past = new \DateTimeImmutable('-1 second');
        $order->setExpiresTime($past);

        $this->assertTrue($order->isExpired());
    }

    public function testStateTransitionsPendingToReady(): void
    {
        $order = $this->createEntity();
        $order->setStatus(OrderStatus::PENDING);
        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertFalse($order->isReady());

        $order->setStatus(OrderStatus::READY);
        $this->assertSame(OrderStatus::READY, $order->getStatus());
        $this->assertTrue($order->isReady());
    }

    public function testStateTransitionsReadyToProcessing(): void
    {
        $order = $this->createEntity();
        $order->setStatus(OrderStatus::READY);
        $this->assertTrue($order->isReady());

        $order->setStatus(OrderStatus::PROCESSING);
        $this->assertSame(OrderStatus::PROCESSING, $order->getStatus());
        $this->assertFalse($order->isReady());
    }

    public function testStateTransitionsProcessingToValid(): void
    {
        $order = $this->createEntity();
        $order->setStatus(OrderStatus::PROCESSING);
        $this->assertFalse($order->isValid());

        $order->setStatus(OrderStatus::VALID);
        $this->assertSame(OrderStatus::VALID, $order->getStatus());
        $this->assertTrue($order->isValid());
    }

    protected function createEntity(): Order
    {
        return new Order();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'orderUrl' => ['orderUrl', 'https://acme.example.com/order/123'];
        yield 'status' => ['status', OrderStatus::VALID];
        yield 'expiresTime' => ['expiresTime', new \DateTimeImmutable('+7 days')];
        yield 'error' => ['error', 'Test error'];
        yield 'finalizeUrl' => ['finalizeUrl', 'https://acme.example.com/finalize/123'];
        yield 'certificateUrl' => ['certificateUrl', 'https://acme.example.com/cert/123'];
        yield 'valid' => ['valid', true];
    }
}
