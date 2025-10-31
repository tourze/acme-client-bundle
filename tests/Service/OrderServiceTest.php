<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeOperationException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\OrderService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(OrderService::class)]
#[RunTestsInSeparateProcesses]
final class OrderServiceTest extends AbstractIntegrationTestCase
{
    private OrderService $service;

    /** @var AcmeApiClient&MockObject */
    private AcmeApiClient $apiClient;

    protected function onSetUp(): void
    {
        /*
         * 使用具体类 AcmeApiClient 的 Mock 对象
         * 原因：AcmeApiClient 是核心 API 客户端服务类，没有对应的接口抽象
         * 合理性：在单元测试中需要隔离外部 ACME API 调用，使用 Mock 是必要的测试实践
         * 替代方案：可以考虑为 AcmeApiClient 创建接口抽象，但会增加代码复杂度
         */
        $this->apiClient = $this->createMock(AcmeApiClient::class);

        // 将 Mock 对象设置到容器中
        self::getContainer()->set(AcmeApiClient::class, $this->apiClient);

        // 获取使用 Mock 的服务实例
        $this->service = self::getService(OrderService::class);
    }

    public function testCreateOrder(): void
    {
        $account = $this->createAccount();
        $domains = ['example.com', 'www.example.com'];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with('newOrder', self::anything(), self::anything(), self::anything())
            ->willReturn([
                'status' => 'pending',
                'expires' => '2023-12-31T23:59:59Z',
                'identifiers' => [
                    ['type' => 'dns', 'value' => 'example.com'],
                    ['type' => 'dns', 'value' => 'www.example.com'],
                ],
                'authorizations' => [],
                'finalize' => 'https://acme-staging-v02.api.letsencrypt.org/acme/finalize/123/456',
                'location' => 'https://acme-staging-v02.api.letsencrypt.org/acme/order/123',
            ])
        ;

        $result = $this->service->createOrder($account, $domains);

        $this->assertInstanceOf(Order::class, $result);
        $this->assertEquals(OrderStatus::PENDING, $result->getStatus());
        $this->assertSame($account, $result->getAccount());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testFinalizeOrder(): void
    {
        $order = $this->createOrder();
        $csr = 'test-certificate-signing-request';

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(self::anything(), self::anything(), self::anything(), self::anything())
            ->willReturn(['status' => 'processing', 'certificate' => 'https://acme-staging-v02.api.letsencrypt.org/acme/cert/123'])
        ;

        $result = $this->service->finalizeOrder($order, $csr);

        $this->assertSame($order, $result);
        $this->assertEquals(OrderStatus::PROCESSING, $order->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($order);
    }

    public function testFindOrdersByStatus(): void
    {
        // 先创建一些测试订单
        $order1 = $this->createOrder();
        $order1->setStatus(OrderStatus::VALID);
        $repository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $repository);
        $repository->save($order1);

        $order2 = $this->createOrder();
        $order2->setStatus(OrderStatus::VALID);
        $repository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $repository);
        $repository->save($order2);

        $result = $this->service->findOrdersByStatus(OrderStatus::VALID);
        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $order) {
            $this->assertEquals(OrderStatus::VALID, $order->getStatus());
        }
    }

    public function testFindOrdersByAccount(): void
    {
        $account = $this->createAccount();
        $accountRepository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $accountRepository);
        $accountRepository->save($account);

        // 创建订单
        $order1 = $this->createOrder($account);
        $repository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $repository);
        $repository->save($order1);

        $order2 = $this->createOrder($account);
        $repository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $repository);
        $repository->save($order2);

        $result = $this->service->findOrdersByAccount($account);
        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $order) {
            $this->assertSame($account, $order->getAccount());
        }
    }

    public function testIsOrderReady(): void
    {
        $order = $this->createOrder();
        $order->setStatus(OrderStatus::READY);
        $order->setFinalizeUrl('https://acme.example.com/order/test/finalize');
        $order->setExpiresTime(new \DateTimeImmutable('2025-12-31T23:59:59Z'));

        // 添加有效的授权信息
        $identifier = new Identifier();
        $identifier->setValue('example.com');
        $identifier->setType('dns');

        $authorization = new Authorization();
        $authorization->setIdentifier($identifier);
        $authorization->setStatus(AuthorizationStatus::VALID);
        $authorization->setExpiresTime(new \DateTimeImmutable('2025-12-31T23:59:59Z'));
        $authorization->setAuthorizationUrl('https://acme.example.com/authz/123');
        $authorization->setValid(true);

        // 添加有效的挑战
        $challenge = new Challenge();
        $challenge->setAuthorization($authorization);
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::VALID);
        $challenge->setToken('test-token');
        $challenge->setKeyAuthorization('test-key-auth');
        $challenge->setChallengeUrl('https://acme.example.com/challenge/123');
        $authorization->addChallenge($challenge);

        $order->addAuthorization($authorization);

        $isReady = $this->service->isOrderReady($order);

        $this->assertTrue($isReady);
    }

    public function testIsOrderReadyWithPendingStatus(): void
    {
        $order = new Order();
        $order->setOrderUrl('https://acme.example.com/order/test');
        $order->setStatus(OrderStatus::PENDING);

        $isReady = $this->service->isOrderReady($order);

        $this->assertFalse($isReady);
    }

    public function testBusinessScenarioOrderLifecycle(): void
    {
        $account = $this->createAccount();
        $domains = ['business.example.com'];

        // 1. 创建订单
        $this->apiClient->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                [
                    'status' => 'pending',
                    'expires' => '2023-12-31T23:59:59Z',
                    'identifiers' => [['type' => 'dns', 'value' => 'business.example.com']],
                    'authorizations' => [],
                    'finalize' => 'https://acme-staging-v02.api.letsencrypt.org/acme/finalize/123/456',
                    'location' => 'https://acme-staging-v02.api.letsencrypt.org/acme/order/123',
                ],
                ['status' => 'valid', 'certificate' => 'https://acme-staging-v02.api.letsencrypt.org/acme/cert/123']
            )
        ;

        $order = $this->service->createOrder($account, $domains);
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());

        // 2. 完成验证后，准备完成订单
        $order->setStatus(OrderStatus::READY);

        // 3. 最终化订单
        $csr = 'business-certificate-signing-request';

        $finalizedOrder = $this->service->finalizeOrder($order, $csr);
        $this->assertEquals(OrderStatus::VALID, $finalizedOrder->getStatus());

        // 验证整个流程中的实体都已持久化
        $this->assertEntityPersisted($finalizedOrder);
    }

    public function testEdgeCasesInvalidOrder(): void
    {
        $order = $this->createOrder();
        $order->setStatus(OrderStatus::INVALID);
        $csr = 'invalid-order-csr';

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willThrowException(new AcmeOperationException('Order is invalid'))
        ;

        $this->expectException(AbstractAcmeException::class);

        $this->service->finalizeOrder($order, $csr);
    }

    private function createAccount(): Account
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setAccountUrl('https://acme.example.com/account/' . uniqid());
        $account->setValid(true);
        $account->setStatus(AccountStatus::VALID);

        return $account;
    }

    private function createOrder(?Account $account = null): Order
    {
        if (null === $account) {
            $account = $this->createAccount();
        }

        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://acme.example.com/order/' . uniqid());
        $order->setFinalizeUrl('https://acme.example.com/order/' . uniqid() . '/finalize');
        $order->setExpiresTime(new \DateTimeImmutable('+7 days'));

        return $order;
    }

    public function testDownloadCertificate(): void
    {
        $order = $this->createOrder();
        $order->setCertificateUrl('https://acme.example.com/cert/123');

        $certificatePem = $this->generateTestCertificate();

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($order->getCertificateUrl())
            ->willReturn(['certificate' => $certificatePem])
        ;

        $result = $this->service->downloadCertificate($order);

        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertSame($order, $result->getOrder());
        $this->assertEquals(CertificateStatus::ISSUED, $result->getStatus());
        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $result->getCertificatePem());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testDownloadCertificateWithoutUrl(): void
    {
        $order = $this->createOrder();
        $order->setCertificateUrl(null);

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Certificate URL not available');

        $this->service->downloadCertificate($order);
    }

    public function testDownloadCertificateEmptyResponse(): void
    {
        $order = $this->createOrder();
        $order->setCertificateUrl('https://acme.example.com/cert/123');

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn(['certificate' => ''])
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Empty certificate received');

        $this->service->downloadCertificate($order);
    }

    public function testFinalizeOrderWithAutoCSR(): void
    {
        $order = $this->createOrder();
        $order->setStatus(OrderStatus::READY);

        // 添加标识符
        $identifier = new Identifier();
        $identifier->setOrder($order);
        $identifier->setValue('example.com');
        $identifier->setType('dns');
        $order->addOrderIdentifier($identifier);

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                $order->getFinalizeUrl(),
                self::callback(function ($payload) {
                    return is_array($payload) && isset($payload['csr']) && '' !== $payload['csr'];
                }),
                self::anything(),
                self::anything()
            )
            ->willReturn(['status' => 'processing'])
        ;

        $result = $this->service->finalizeOrderWithAutoCSR($order);

        $this->assertSame($order, $result);
        $this->assertEquals(OrderStatus::PROCESSING, $order->getStatus());
    }

    public function testFinalizeOrderWithAutoCSRMultipleDomains(): void
    {
        $order = $this->createOrder();
        $order->setStatus(OrderStatus::READY);

        // 添加多个标识符
        $domains = ['example.com', 'www.example.com', 'api.example.com'];
        foreach ($domains as $domain) {
            $identifier = new Identifier();
            $identifier->setOrder($order);
            $identifier->setValue($domain);
            $identifier->setType('dns');
            $order->addOrderIdentifier($identifier);
        }

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'valid', 'certificate' => 'https://acme.example.com/cert/123'])
        ;

        $result = $this->service->finalizeOrderWithAutoCSR($order);

        $this->assertEquals(OrderStatus::VALID, $result->getStatus());
        $this->assertEquals('https://acme.example.com/cert/123', $result->getCertificateUrl());
    }

    public function testRefreshOrderStatus(): void
    {
        $order = $this->createOrder();
        $order->setStatus(OrderStatus::PENDING);

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($order->getOrderUrl())
            ->willReturn([
                'status' => 'ready',
                'expires' => '2025-12-31T23:59:59Z',
                'certificate' => 'https://acme.example.com/cert/new-123',
            ])
        ;

        $result = $this->service->refreshOrderStatus($order);

        $this->assertSame($order, $result);
        $this->assertEquals(OrderStatus::READY, $order->getStatus());
        $this->assertEquals('https://acme.example.com/cert/new-123', $order->getCertificateUrl());
        $this->assertNotNull($order->getExpiresTime());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($order);
    }

    public function testRefreshOrderStatusWithInvalidResponse(): void
    {
        $order = $this->createOrder();

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('Server error'))
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Failed to get order status');

        $this->service->refreshOrderStatus($order);
    }

    public function testBusinessScenarioFullOrderProcess(): void
    {
        $account = $this->createAccount();
        $domains = ['test.example.com'];

        // 1. 创建订单
        $this->apiClient->expects($this->exactly(3))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                // createOrder response
                [
                    'status' => 'pending',
                    'expires' => '2025-12-31T23:59:59Z',
                    'identifiers' => [['type' => 'dns', 'value' => 'test.example.com']],
                    'authorizations' => ['https://acme.example.com/authz/123'],
                    'finalize' => 'https://acme.example.com/order/123/finalize',
                    'location' => 'https://acme.example.com/order/123',
                ],
                // finalizeOrderWithAutoCSR response
                ['status' => 'processing'],
                // 第二次 finalizeOrderWithAutoCSR (after status update)
                ['status' => 'valid', 'certificate' => 'https://acme.example.com/cert/123']
            )
        ;

        $this->apiClient->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                // refreshOrderStatus response
                ['status' => 'ready'],
                // downloadCertificate response
                ['certificate' => $this->generateTestCertificate()]
            )
        ;

        // 创建订单
        $order = $this->service->createOrder($account, $domains);
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());

        // 刷新状态
        $this->service->refreshOrderStatus($order);
        $this->assertEquals(OrderStatus::READY, $order->getStatus());

        // 使用自动CSR完成订单
        $finalizedOrder = $this->service->finalizeOrderWithAutoCSR($order);
        $this->assertEquals(OrderStatus::PROCESSING, $finalizedOrder->getStatus());

        // 再次完成以获取证书URL
        $finalizedOrder->setStatus(OrderStatus::READY);
        $completeOrder = $this->service->finalizeOrderWithAutoCSR($finalizedOrder);
        $this->assertEquals(OrderStatus::VALID, $completeOrder->getStatus());

        // 下载证书
        $certificate = $this->service->downloadCertificate($completeOrder);
        $this->assertInstanceOf(Certificate::class, $certificate);
        $this->assertEquals(CertificateStatus::ISSUED, $certificate->getStatus());
    }

    private function generateTestPrivateKey(): string
    {
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $privateKey) {
            throw new CertificateGenerationException('Failed to generate private key');
        }

        $privateKeyPem = '';
        $result = openssl_pkey_export($privateKey, $privateKeyPem);
        if (!$result || !is_string($privateKeyPem)) {
            throw new CertificateGenerationException('Failed to export private key');
        }

        return $privateKeyPem;
    }

    private function generateTestCertificate(): string
    {
        // 生成私钥
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $privateKey) {
            throw new CertificateGenerationException('Failed to generate private key');
        }

        // 创建证书请求
        $dn = [
            'CN' => 'example.com',
            'O' => 'Test Organization',
            'C' => 'US',
        ];

        $csr = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => 'sha256',
        ]);

        if (false === $csr) {
            throw new CertificateGenerationException('Failed to create CSR');
        }

        if (true === $csr) {
            throw new CertificateGenerationException('Invalid CSR type returned');
        }

        // 生成自签名证书
        $x509 = openssl_csr_sign($csr, null, $privateKey, 365, [
            'digest_alg' => 'sha256',
        ]);

        if (false === $x509) {
            throw new CertificateGenerationException('Failed to sign certificate');
        }

        // 导出证书
        $certificatePem = '';
        $result = openssl_x509_export($x509, $certificatePem);
        if (!$result || !is_string($certificatePem)) {
            throw new CertificateGenerationException('Failed to export certificate');
        }

        return $certificatePem;
    }
}
