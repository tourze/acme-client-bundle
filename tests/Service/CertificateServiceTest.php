<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\CertificateService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CertificateService::class)]
#[RunTestsInSeparateProcesses]
final class CertificateServiceTest extends AbstractIntegrationTestCase
{
    private CertificateService $service;

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
        self::getContainer()->set(AcmeApiClient::class, $this->apiClient);

        $this->service = self::getService(CertificateService::class);
    }

    public function testDownloadCertificate(): void
    {
        $order = $this->createOrder();
        $certificateData = $this->generateTestCertificate();

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($order->getCertificateUrl())
            ->willReturn([
                'certificate' => $certificateData,
                'certificateChain' => 'certificate-chain-data',
            ])
        ;

        $result = $this->service->downloadCertificate($order);

        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertSame($order, $result->getOrder());
        $this->assertEquals(CertificateStatus::ISSUED, $result->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testRevokeCertificate(): void
    {
        $certificate = $this->createCertificate();
        $reason = 1; // keyCompromise

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'revokeCert',
                self::callback(fn ($payload) => is_array($payload)
                    && isset($payload['certificate'], $payload['reason'])
                    && $payload['reason'] === $reason
                ),
                self::anything(),
                self::anything()
            )
            ->willReturn(['status' => 'revoked'])
        ;

        $result = $this->service->revokeCertificate($certificate, $reason);

        $this->assertSame($certificate, $result);
        $this->assertEquals(CertificateStatus::REVOKED, $certificate->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($certificate);
    }

    public function testFindCertificatesByStatus(): void
    {
        // 先创建一些测试证书
        $certificate1 = $this->createCertificate();
        $certificate1->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        $certificate2 = $this->createCertificate();
        $certificate2->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        $result = $this->service->findCertificatesByStatus(CertificateStatus::ISSUED);
        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $certificate) {
            $this->assertEquals(CertificateStatus::ISSUED, $certificate->getStatus());
        }
    }

    public function testFindCertificatesByOrder(): void
    {
        // 创建第一个订单和证书
        $order1 = $this->createOrder();
        $orderRepository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $orderRepository);
        $orderRepository->save($order1);

        $certificate1 = $this->createCertificate();
        $certificate1->setOrder($order1);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        // 创建第二个订单和证书
        $order2 = $this->createOrder();
        $orderRepository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $orderRepository);
        $orderRepository->save($order2);

        $certificate2 = $this->createCertificate();
        $certificate2->setOrder($order2);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        // 测试查找第一个订单的证书
        $result1 = $this->service->findCertificatesByOrder($order1);
        $this->assertCount(1, $result1);
        $this->assertSame($order1, $result1[0]->getOrder());

        // 测试查找第二个订单的证书
        $result2 = $this->service->findCertificatesByOrder($order2);
        $this->assertCount(1, $result2);
        $this->assertSame($order2, $result2[0]->getOrder());
    }

    public function testIsCertificateValid(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem($this->generateTestCertificate());
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setExpiresTime(new \DateTimeImmutable('+30 days'));
        $certificate->setNotAfterTime(new \DateTimeImmutable('+30 days'));

        $isValid = $this->service->isCertificateValid($certificate);

        $this->assertTrue($isValid);
    }

    public function testIsCertificateValidWithExpired(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem('-----BEGIN CERTIFICATE-----\nTest Certificate\n-----END CERTIFICATE-----');
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setExpiresTime(new \DateTimeImmutable('-1 day'));

        $isValid = $this->service->isCertificateValid($certificate);

        $this->assertFalse($isValid);
    }

    public function testIsCertificateValidWithRevokedStatus(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem('-----BEGIN CERTIFICATE-----\nTest Certificate\n-----END CERTIFICATE-----');
        $certificate->setStatus(CertificateStatus::REVOKED);
        $certificate->setExpiresTime(new \DateTimeImmutable('+30 days'));

        $isValid = $this->service->isCertificateValid($certificate);

        $this->assertFalse($isValid);
    }

    public function testBusinessScenarioCertificateLifecycle(): void
    {
        $order = $this->createOrder();
        $certificateData = $this->generateTestCertificate();

        // 1. 下载证书
        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn([
                'certificate' => $certificateData,
                'certificateChain' => 'chain-data',
            ])
        ;

        $certificate = $this->service->downloadCertificate($order);
        $this->assertEquals(CertificateStatus::ISSUED, $certificate->getStatus());

        // 2. 吊销证书
        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'revoked'])
        ;

        $revokedCertificate = $this->service->revokeCertificate($certificate, 5); // cessationOfOperation
        $this->assertEquals(CertificateStatus::REVOKED, $revokedCertificate->getStatus());

        // 验证整个生命周期中的实体都已持久化
        $this->assertEntityPersisted($revokedCertificate);
    }

    public function testEdgeCasesAlreadyRevokedCertificate(): void
    {
        $certificate = $this->createCertificate();
        $certificate->setStatus(CertificateStatus::REVOKED);

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willThrowException(new AcmeServerException('Certificate already revoked'))
        ;

        $this->expectException(AbstractAcmeException::class);

        $this->service->revokeCertificate($certificate, 1); // keyCompromise
    }

    private function createOrder(): Order
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setAccountUrl('https://acme.example.com/account/' . uniqid());

        $order = new Order();
        $order->setAccount($account);
        $order->setOrderUrl('https://acme.example.com/order/' . uniqid());
        $order->setCertificateUrl('https://acme.example.com/certificate/' . uniqid());
        $order->setExpiresTime(new \DateTimeImmutable('+7 days'));

        return $order;
    }

    private function createCertificate(): Certificate
    {
        $order = $this->createOrder();

        $certificate = new Certificate();
        $certificate->setOrder($order);
        $certificate->setStatus(CertificateStatus::ISSUED);
        // 使用真实的证书PEM格式以确保pemToDer方法正常工作
        $certificate->setCertificatePem($this->generateTestCertificate());
        $certificate->setCertificateData($this->generateTestCertificate());
        $certificate->setIssuedTime(new \DateTimeImmutable());
        $certificate->setExpiresTime(new \DateTimeImmutable('+90 days'));
        $certificate->setSerialNumber('test-serial-' . uniqid());

        return $certificate;
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

        $result = openssl_pkey_export($privateKey, $privateKeyPem);
        if (!$result) {
            throw new CertificateGenerationException('Failed to export private key');
        }

        return $privateKeyPem;
    }

    public function testFindCertificatesByDomain(): void
    {
        // 创建测试证书
        $certificate1 = $this->createCertificate();
        $certificate1->setDomains(['example.com', 'www.example.com']);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        $certificate2 = $this->createCertificate();
        $certificate2->setDomains(['test.com', 'www.test.com']);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        $certificate3 = $this->createCertificate();
        $certificate3->setDomains(['example.com', 'api.example.com']);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate3);

        // 查找包含特定域名的证书
        $result = $this->service->findCertificatesByDomain('example.com');
        $this->assertGreaterThanOrEqual(2, count($result));

        // 验证返回的证书都包含指定域名
        foreach ($result as $cert) {
            $this->assertContains('example.com', $cert->getDomains());
        }
    }

    public function testFindCertificatesByDomainEmpty(): void
    {
        // 查找不存在的域名
        $result = $this->service->findCertificatesByDomain('nonexistent.com');
        $this->assertEmpty($result);
    }

    public function testFindExpiringCertificates(): void
    {
        // 创建即将过期的证书
        $certificate1 = $this->createCertificate();
        $certificate1->setNotAfterTime(new \DateTimeImmutable('+15 days'));
        $certificate1->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        // 创建还有很长时间才过期的证书
        $certificate2 = $this->createCertificate();
        $certificate2->setNotAfterTime(new \DateTimeImmutable('+90 days'));
        $certificate2->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        // 创建已经过期的证书
        $certificate3 = $this->createCertificate();
        $certificate3->setNotAfterTime(new \DateTimeImmutable('-5 days'));
        $certificate3->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate3);

        // 查找30天内即将过期的证书
        $result = $this->service->findExpiringCertificates(30);
        $this->assertGreaterThanOrEqual(1, count($result));

        // 验证返回的证书都在30天内过期
        $threshold = new \DateTimeImmutable('+30 days');
        foreach ($result as $cert) {
            $this->assertLessThanOrEqual($threshold, $cert->getNotAfterTime());
        }
    }

    public function testFindExpiringCertificatesWithCustomDays(): void
    {
        // 创建不同过期时间的证书
        $certificate1 = $this->createCertificate();
        $certificate1->setNotAfterTime(new \DateTimeImmutable('+5 days'));
        $certificate1->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        $certificate2 = $this->createCertificate();
        $certificate2->setNotAfterTime(new \DateTimeImmutable('+10 days'));
        $certificate2->setStatus(CertificateStatus::ISSUED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        // 查找7天内即将过期的证书
        $result = $this->service->findExpiringCertificates(7);
        $this->assertGreaterThanOrEqual(1, count($result));

        // 验证只返回5天过期的证书
        foreach ($result as $cert) {
            $this->assertLessThanOrEqual(new \DateTimeImmutable('+7 days'), $cert->getNotAfterTime());
        }
    }

    public function testFindValidCertificates(): void
    {
        // 创建有效证书
        $certificate1 = $this->createCertificate();
        $certificate1->setStatus(CertificateStatus::VALID);
        $certificate1->setNotAfterTime(new \DateTimeImmutable('+90 days'));
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        $certificate2 = $this->createCertificate();
        $certificate2->setStatus(CertificateStatus::ISSUED);
        $certificate2->setNotAfterTime(new \DateTimeImmutable('+60 days'));
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        // 创建无效证书
        $certificate3 = $this->createCertificate();
        $certificate3->setStatus(CertificateStatus::REVOKED);
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate3);

        // 查找所有有效证书
        $result = $this->service->findValidCertificates();
        $this->assertGreaterThanOrEqual(2, count($result));

        // 验证返回的证书状态都是有效的
        foreach ($result as $cert) {
            $this->assertContains($cert->getStatus(), [CertificateStatus::VALID, CertificateStatus::ISSUED]);
        }
    }

    public function testFindValidCertificatesOrderByNotAfterTime(): void
    {
        // 创建多个有效证书，设置不同的过期时间
        $certificate1 = $this->createCertificate();
        $certificate1->setStatus(CertificateStatus::ISSUED);
        $certificate1->setNotAfterTime(new \DateTimeImmutable('+30 days'));
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate1);

        $certificate2 = $this->createCertificate();
        $certificate2->setStatus(CertificateStatus::ISSUED);
        $certificate2->setNotAfterTime(new \DateTimeImmutable('+60 days'));
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate2);

        $certificate3 = $this->createCertificate();
        $certificate3->setStatus(CertificateStatus::ISSUED);
        $certificate3->setNotAfterTime(new \DateTimeImmutable('+90 days'));
        $repository = self::getService(CertificateRepository::class);
        $this->assertInstanceOf(CertificateRepository::class, $repository);
        $repository->save($certificate3);

        // 获取所有有效证书
        $result = $this->service->findValidCertificates();

        // 验证按过期时间升序排列
        $this->assertGreaterThanOrEqual(3, count($result));
        $previousTime = null;
        foreach ($result as $cert) {
            if (null !== $previousTime) {
                $this->assertGreaterThanOrEqual($previousTime, $cert->getNotAfterTime());
            }
            $previousTime = $cert->getNotAfterTime();
        }
    }

    public function testGenerateCsrWithInvalidPrivateKey(): void
    {
        $domains = ['example.com'];
        $invalidPrivateKey = 'invalid-private-key';

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Invalid private key for CSR generation');

        $this->service->generateCsr($domains, $invalidPrivateKey);
    }

    public function testValidateCertificate(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem($this->generateTestCertificate());
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setExpiresTime(new \DateTimeImmutable('+30 days'));
        $certificate->setNotAfterTime(new \DateTimeImmutable('+30 days'));

        $isValid = $this->service->validateCertificate($certificate);

        $this->assertTrue($isValid);
    }

    public function testValidateCertificateWithExpired(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem($this->generateTestCertificate());
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setExpiresTime(new \DateTimeImmutable('-1 day'));
        $certificate->setNotAfterTime(new \DateTimeImmutable('-1 day'));

        $isValid = $this->service->validateCertificate($certificate);

        $this->assertFalse($isValid);
    }

    public function testValidateCertificateWithInvalidStatus(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem($this->generateTestCertificate());
        $certificate->setStatus(CertificateStatus::REVOKED);
        $certificate->setExpiresTime(new \DateTimeImmutable('+30 days'));

        $isValid = $this->service->validateCertificate($certificate);

        $this->assertFalse($isValid);
    }

    public function testValidateCertificateWithInvalidPem(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem('invalid-certificate-pem');
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setExpiresTime(new \DateTimeImmutable('+30 days'));

        $isValid = $this->service->validateCertificate($certificate);

        $this->assertFalse($isValid);
    }

    public function testValidateCertificateWithChain(): void
    {
        $certificate = new Certificate();
        $certificate->setCertificatePem($this->generateTestCertificate());
        $certificate->setCertificateChainPem('-----BEGIN CERTIFICATE-----\nchain\n-----END CERTIFICATE-----');
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setExpiresTime(new \DateTimeImmutable('+30 days'));
        $certificate->setNotAfterTime(new \DateTimeImmutable('+30 days'));

        $isValid = $this->service->validateCertificate($certificate);

        $this->assertTrue($isValid);
    }

    private function generateTestCertificate(): string
    {
        // 生成私钥
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

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
            throw new CertificateGenerationException('Failed to create certificate signing request');
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
        $result = openssl_x509_export($x509, $certificatePem);
        if (!$result) {
            throw new CertificateGenerationException('Failed to export certificate');
        }

        return $certificatePem;
    }
}
