<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;

/**
 * Order 实体测试类
 */
class OrderTest extends TestCase
{
    private Order $order;

    protected function setUp(): void
    {
        $this->order = new Order();
    }

    public function test_constructor_defaultValues(): void
    {
        $this->assertNull($this->order->getId());
        $this->assertNull($this->order->getAccount());
        $this->assertSame(OrderStatus::PENDING, $this->order->getStatus());
        $this->assertNull($this->order->getExpiresTime());
        $this->assertNull($this->order->getError());
        $this->assertNull($this->order->getFinalizeUrl());
        $this->assertNull($this->order->getCertificateUrl());
        $this->assertFalse($this->order->isValid());
        $this->assertInstanceOf(ArrayCollection::class, $this->order->getOrderIdentifiers());
        $this->assertTrue($this->order->getOrderIdentifiers()->isEmpty());
        $this->assertInstanceOf(ArrayCollection::class, $this->order->getAuthorizations());
        $this->assertTrue($this->order->getAuthorizations()->isEmpty());
        $this->assertNull($this->order->getCertificate());
    }

    public function test_account_getterSetter(): void
    {
        $account = $this->createMock(Account::class);
        $result = $this->order->setAccount($account);

        $this->assertSame($this->order, $result);
        $this->assertSame($account, $this->order->getAccount());
    }

    public function test_account_setToNull(): void
    {
        $account = $this->createMock(Account::class);
        $this->order->setAccount($account);

        $this->order->setAccount(null);
        $this->assertNull($this->order->getAccount());
    }

    public function test_orderUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/acme/order/123456';
        $result = $this->order->setOrderUrl($url);

        $this->assertSame($this->order, $result);
        $this->assertSame($url, $this->order->getOrderUrl());
    }

    public function test_status_getterSetter(): void
    {
        $this->assertSame(OrderStatus::PENDING, $this->order->getStatus());

        $result = $this->order->setStatus(OrderStatus::READY);
        $this->assertSame($this->order, $result);
        $this->assertSame(OrderStatus::READY, $this->order->getStatus());
    }

    public function test_status_automaticValidFlag(): void
    {
        // 设置为 VALID 时自动设置 valid 标志为 true
        $this->order->setStatus(OrderStatus::VALID);
        $this->assertTrue($this->order->isValid());

        // 设置为其他状态时 valid 标志为 false
        $this->order->setStatus(OrderStatus::PENDING);
        $this->assertFalse($this->order->isValid());

        $this->order->setStatus(OrderStatus::READY);
        $this->assertFalse($this->order->isValid());

        $this->order->setStatus(OrderStatus::PROCESSING);
        $this->assertFalse($this->order->isValid());

        $this->order->setStatus(OrderStatus::INVALID);
        $this->assertFalse($this->order->isValid());
    }

    public function test_expiresTime_getterSetter(): void
    {
        $this->assertNull($this->order->getExpiresTime());

        $expiry = new \DateTimeImmutable('+7 days');
        $result = $this->order->setExpiresTime($expiry);

        $this->assertSame($this->order, $result);
        $this->assertSame($expiry, $this->order->getExpiresTime());
    }

    public function test_expiresTime_setToNull(): void
    {
        $this->order->setExpiresTime(new \DateTimeImmutable());
        $this->order->setExpiresTime(null);

        $this->assertNull($this->order->getExpiresTime());
    }

    public function test_error_getterSetter(): void
    {
        $this->assertNull($this->order->getError());

        $error = 'Domain validation failed';
        $result = $this->order->setError($error);

        $this->assertSame($this->order, $result);
        $this->assertSame($error, $this->order->getError());
    }

    public function test_error_setToNull(): void
    {
        $this->order->setError('Some error');
        $this->order->setError(null);

        $this->assertNull($this->order->getError());
    }

    public function test_finalizeUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/acme/finalize/123456';
        $result = $this->order->setFinalizeUrl($url);

        $this->assertSame($this->order, $result);
        $this->assertSame($url, $this->order->getFinalizeUrl());
    }

    public function test_certificateUrl_getterSetter(): void
    {
        $url = 'https://acme-v02.api.letsencrypt.org/acme/cert/123456';
        $result = $this->order->setCertificateUrl($url);

        $this->assertSame($this->order, $result);
        $this->assertSame($url, $this->order->getCertificateUrl());
    }

    public function test_valid_getterSetter(): void
    {
        $this->assertFalse($this->order->isValid());

        $result = $this->order->setValid(true);
        $this->assertSame($this->order, $result);
        $this->assertTrue($this->order->isValid());

        $this->order->setValid(false);
        $this->assertFalse($this->order->isValid());
    }

    public function test_addOrderIdentifier(): void
    {
        $identifier = $this->createMock(Identifier::class);

        $result = $this->order->addOrderIdentifier($identifier);

        $this->assertSame($this->order, $result);
        $this->assertTrue($this->order->getOrderIdentifiers()->contains($identifier));
        $this->assertSame(1, $this->order->getOrderIdentifiers()->count());
    }

    public function test_addOrderIdentifier_preventDuplicates(): void
    {
        $identifier = $this->createMock(Identifier::class);

        $this->order->addOrderIdentifier($identifier);
        $this->order->addOrderIdentifier($identifier);

        $this->assertSame(1, $this->order->getOrderIdentifiers()->count());
        $this->assertTrue($this->order->getOrderIdentifiers()->contains($identifier));
    }

    public function test_removeOrderIdentifier(): void
    {
        $identifier = $this->createMock(Identifier::class);

        $this->order->getOrderIdentifiers()->add($identifier);
        $this->assertTrue($this->order->getOrderIdentifiers()->contains($identifier));

        $result = $this->order->removeOrderIdentifier($identifier);

        $this->assertSame($this->order, $result);
        $this->assertFalse($this->order->getOrderIdentifiers()->contains($identifier));
        $this->assertSame(0, $this->order->getOrderIdentifiers()->count());
    }

    public function test_addAuthorization(): void
    {
        $authorization = $this->createMock(Authorization::class);

        $result = $this->order->addAuthorization($authorization);

        $this->assertSame($this->order, $result);
        $this->assertTrue($this->order->getAuthorizations()->contains($authorization));
        $this->assertSame(1, $this->order->getAuthorizations()->count());
    }

    public function test_addAuthorization_preventDuplicates(): void
    {
        $authorization = $this->createMock(Authorization::class);

        $this->order->addAuthorization($authorization);
        $this->order->addAuthorization($authorization);

        $this->assertSame(1, $this->order->getAuthorizations()->count());
        $this->assertTrue($this->order->getAuthorizations()->contains($authorization));
    }

    public function test_removeAuthorization(): void
    {
        $authorization = $this->createMock(Authorization::class);

        $this->order->getAuthorizations()->add($authorization);
        $this->assertTrue($this->order->getAuthorizations()->contains($authorization));

        $result = $this->order->removeAuthorization($authorization);

        $this->assertSame($this->order, $result);
        $this->assertFalse($this->order->getAuthorizations()->contains($authorization));
        $this->assertSame(0, $this->order->getAuthorizations()->count());
    }

    public function test_certificate_getterSetter(): void
    {
        $certificate = $this->createMock(Certificate::class);

        $result = $this->order->setCertificate($certificate);

        $this->assertSame($this->order, $result);
        $this->assertSame($certificate, $this->order->getCertificate());
    }

    public function test_certificate_setToNull(): void
    {
        $certificate = $this->createMock(Certificate::class);
        $this->order->setCertificate($certificate);

        $this->order->setCertificate(null);
        $this->assertNull($this->order->getCertificate());
    }

    public function test_isExpired_withFutureDate(): void
    {
        $future = new \DateTimeImmutable('+1 hour');
        $this->order->setExpiresTime($future);

        $this->assertFalse($this->order->isExpired());
    }

    public function test_isExpired_withPastDate(): void
    {
        $past = new \DateTimeImmutable('-1 hour');
        $this->order->setExpiresTime($past);

        $this->assertTrue($this->order->isExpired());
    }

    public function test_isReady(): void
    {
        $this->assertFalse($this->order->isReady());

        $this->order->setStatus(OrderStatus::PENDING);
        $this->assertFalse($this->order->isReady());

        $this->order->setStatus(OrderStatus::READY);
        $this->assertTrue($this->order->isReady());

        $this->order->setStatus(OrderStatus::VALID);
        $this->assertFalse($this->order->isReady());
    }

    public function test_isInvalid(): void
    {
        $this->assertFalse($this->order->isInvalid());

        $this->order->setStatus(OrderStatus::PENDING);
        $this->assertFalse($this->order->isInvalid());

        $this->order->setStatus(OrderStatus::INVALID);
        $this->assertTrue($this->order->isInvalid());

        $this->order->setStatus(OrderStatus::VALID);
        $this->assertFalse($this->order->isInvalid());
    }

    public function test_areAllAuthorizationsValid_empty(): void
    {
        $this->assertTrue($this->order->areAllAuthorizationsValid());
    }

    public function test_areAllAuthorizationsValid_withValidAuthorizations(): void
    {
        $auth1 = $this->createMock(Authorization::class);
        $auth2 = $this->createMock(Authorization::class);

        $this->order->getAuthorizations()->add($auth1);
        $this->order->getAuthorizations()->add($auth2);

        // 由于实际方法会调用 Authorization::isValid()，而 mock 对象默认返回 false
        // 这里测试的是默认行为：当有授权但未显式设置为有效时，应该返回 false
        $this->assertFalse($this->order->areAllAuthorizationsValid());
    }

    public function test_toString_withoutId(): void
    {
        $this->order->setStatus(OrderStatus::PENDING);

        $expected = 'Order #0 (pending)';
        $this->assertSame($expected, (string)$this->order);
    }

    public function test_toString_withDifferentStatus(): void
    {
        $this->order->setStatus(OrderStatus::READY);

        $expected = 'Order #0 (ready)';
        $this->assertSame($expected, (string)$this->order);
    }

    public function test_stringableInterface(): void
    {
        $this->assertInstanceOf(\Stringable::class, $this->order);
    }

    public function test_fluentInterface_chaining(): void
    {
        $account = $this->createMock(Account::class);
        $orderUrl = 'https://acme-v02.api.letsencrypt.org/acme/order/123456';
        $finalizeUrl = 'https://acme-v02.api.letsencrypt.org/acme/finalize/123456';
        $certUrl = 'https://acme-v02.api.letsencrypt.org/acme/cert/123456';
        $expiry = new \DateTimeImmutable('+7 days');

        $result = $this->order
            ->setAccount($account)
            ->setOrderUrl($orderUrl)
            ->setStatus(OrderStatus::READY)
            ->setExpiresTime($expiry)
            ->setFinalizeUrl($finalizeUrl)
            ->setCertificateUrl($certUrl)
            ->setValid(true);

        $this->assertSame($this->order, $result);
        $this->assertSame($account, $this->order->getAccount());
        $this->assertSame($orderUrl, $this->order->getOrderUrl());
        $this->assertSame(OrderStatus::READY, $this->order->getStatus());
        $this->assertSame($expiry, $this->order->getExpiresTime());
        $this->assertSame($finalizeUrl, $this->order->getFinalizeUrl());
        $this->assertSame($certUrl, $this->order->getCertificateUrl());
        $this->assertTrue($this->order->isValid());
    }

    public function test_businessScenario_orderCreation(): void
    {
        $account = $this->createMock(Account::class);

        $this->order
            ->setAccount($account)
            ->setOrderUrl('https://acme-v02.api.letsencrypt.org/acme/order/123456')
            ->setStatus(OrderStatus::PENDING)
            ->setExpiresTime(new \DateTimeImmutable('+7 days'));

        $this->assertSame(OrderStatus::PENDING, $this->order->getStatus());
        $this->assertFalse($this->order->isValid());
        $this->assertFalse($this->order->isReady());
        $this->assertFalse($this->order->isInvalid());
        $this->assertFalse($this->order->isExpired());
    }

    public function test_businessScenario_orderProgression(): void
    {
        // 订单创建
        $this->order->setStatus(OrderStatus::PENDING);
        $this->assertSame(OrderStatus::PENDING, $this->order->getStatus());
        $this->assertFalse($this->order->isValid());

        // 授权完成，订单准备就绪
        $this->order->setStatus(OrderStatus::READY);
        $this->assertTrue($this->order->isReady());
        $this->assertFalse($this->order->isValid());

        // 处理中
        $this->order->setStatus(OrderStatus::PROCESSING);
        $this->assertFalse($this->order->isReady());
        $this->assertFalse($this->order->isValid());

        // 完成
        $this->order->setStatus(OrderStatus::VALID);
        $this->assertTrue($this->order->isValid());
        $this->assertFalse($this->order->isReady());
    }

    public function test_businessScenario_orderFailure(): void
    {
        $this->order
            ->setStatus(OrderStatus::INVALID)
            ->setError('Domain validation failed for example.com');

        $this->assertTrue($this->order->isInvalid());
        $this->assertFalse($this->order->isValid());
        $this->assertStringContainsString('Domain validation failed', $this->order->getError());
    }

    public function test_businessScenario_multipleIdentifiers(): void
    {
        $id1 = $this->createMock(Identifier::class);
        $id2 = $this->createMock(Identifier::class);
        $id3 = $this->createMock(Identifier::class);

        $this->order
            ->addOrderIdentifier($id1)
            ->addOrderIdentifier($id2)
            ->addOrderIdentifier($id3);

        $this->assertSame(3, $this->order->getOrderIdentifiers()->count());
        $this->assertTrue($this->order->getOrderIdentifiers()->contains($id1));
        $this->assertTrue($this->order->getOrderIdentifiers()->contains($id2));
        $this->assertTrue($this->order->getOrderIdentifiers()->contains($id3));
    }

    public function test_businessScenario_withCertificate(): void
    {
        $certificate = $this->createMock(Certificate::class);

        $this->order
            ->setStatus(OrderStatus::VALID)
            ->setCertificate($certificate)
            ->setCertificateUrl('https://acme-v02.api.letsencrypt.org/acme/cert/123456');

        $this->assertTrue($this->order->isValid());
        $this->assertSame($certificate, $this->order->getCertificate());
        $this->assertNotNull($this->order->getCertificateUrl());
    }

    public function test_edgeCases_emptyUrls(): void
    {
        $this->order->setOrderUrl('');
        $this->assertSame('', $this->order->getOrderUrl());
    }

    public function test_edgeCases_longError(): void
    {
        $longError = str_repeat('Error message. ', 100);
        $this->order->setError($longError);

        $this->assertSame($longError, $this->order->getError());
    }

    public function test_edgeCases_expiresTimeEdgeCase(): void
    {
        // 设置过期时间为1秒后，确保不会过期
        $future = new \DateTimeImmutable('+1 second');
        $this->order->setExpiresTime($future);

        $this->assertFalse($this->order->isExpired());

        // 设置过期时间为1秒前，确保已过期
        $past = new \DateTimeImmutable('-1 second');
        $this->order->setExpiresTime($past);

        $this->assertTrue($this->order->isExpired());
    }

    public function test_stateTransitions_pendingToReady(): void
    {
        $this->order->setStatus(OrderStatus::PENDING);
        $this->assertSame(OrderStatus::PENDING, $this->order->getStatus());
        $this->assertFalse($this->order->isReady());

        $this->order->setStatus(OrderStatus::READY);
        $this->assertSame(OrderStatus::READY, $this->order->getStatus());
        $this->assertTrue($this->order->isReady());
    }

    public function test_stateTransitions_readyToProcessing(): void
    {
        $this->order->setStatus(OrderStatus::READY);
        $this->assertTrue($this->order->isReady());

        $this->order->setStatus(OrderStatus::PROCESSING);
        $this->assertSame(OrderStatus::PROCESSING, $this->order->getStatus());
        $this->assertFalse($this->order->isReady());
    }

    public function test_stateTransitions_processingToValid(): void
    {
        $this->order->setStatus(OrderStatus::PROCESSING);
        $this->assertFalse($this->order->isValid());

        $this->order->setStatus(OrderStatus::VALID);
        $this->assertSame(OrderStatus::VALID, $this->order->getStatus());
        $this->assertTrue($this->order->isValid());
    }
}
