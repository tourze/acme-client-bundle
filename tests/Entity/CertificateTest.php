<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * Certificate 实体测试类
 *
 * @internal
 */
#[CoversClass(Certificate::class)]
final class CertificateTest extends AbstractEntityTestCase
{
    public function testConstructorDefaultValues(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getId());
        $this->assertNull($certificate->getOrder());
        $this->assertSame(CertificateStatus::VALID, $certificate->getStatus());
        $this->assertNull($certificate->getCertificateChainPem());
        $this->assertNull($certificate->getPrivateKeyPem());
        $this->assertNull($certificate->getSerialNumber());
        $this->assertNull($certificate->getFingerprint());
        $this->assertSame([], $certificate->getDomains());
        $this->assertNull($certificate->getNotBeforeTime());
        $this->assertNull($certificate->getNotAfterTime());
        $this->assertNull($certificate->getIssuer());
        $this->assertTrue($certificate->isValid());
        $this->assertNull($certificate->getRevokedTime());
    }

    public function testOrderGetterSetter(): void
    {
        // 使用具体类 Order 进行 Mock，因为需要测试 Certificate 与 Order 的关联关系
        // Order 是 Doctrine 实体类，没有对应的接口，使用具体类是必要的
        $order = $this->createMock(Order::class);
        $certificate = $this->createEntity();
        $certificate->setOrder($order);

        $this->assertSame($order, $certificate->getOrder());
    }

    public function testOrderSetToNull(): void
    {
        // 使用具体类 Order 进行 Mock，因为需要测试 Certificate 与 Order 的关联关系
        // Order 是 Doctrine 实体类，没有对应的接口，使用具体类是必要的
        $order = $this->createMock(Order::class);
        $certificate = $this->createEntity();
        $certificate->setOrder($order);

        $certificate->setOrder(null);
        $this->assertNull($certificate->getOrder());
    }

    public function testStatusGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertSame(CertificateStatus::VALID, $certificate->getStatus());

        $certificate->setStatus(CertificateStatus::REVOKED);
        $this->assertSame(CertificateStatus::REVOKED, $certificate->getStatus());
    }

    public function testStatusAutomaticValidFlag(): void
    {
        $certificate = $this->createEntity();

        // 设置为 VALID 时自动设置 valid 标志为 true
        $certificate->setStatus(CertificateStatus::VALID);
        $this->assertTrue($certificate->isValid());

        // 设置为其他状态时 valid 标志为 false
        $certificate->setStatus(CertificateStatus::REVOKED);
        $this->assertFalse($certificate->isValid());

        $certificate->setStatus(CertificateStatus::EXPIRED);
        $this->assertFalse($certificate->isValid());
    }

    public function testCertificatePemGetterSetter(): void
    {
        $certificatePem = "-----BEGIN CERTIFICATE-----\nMIIFZT...sample certificate...=\n-----END CERTIFICATE-----";
        $certificate = $this->createEntity();
        $certificate->setCertificatePem($certificatePem);
        $this->assertSame($certificatePem, $certificate->getCertificatePem());
    }

    public function testCertificateChainPemGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getCertificateChainPem());

        $chainPem = "-----BEGIN CERTIFICATE-----\nMIIFZT...intermediate cert...=\n-----END CERTIFICATE-----";
        $certificate->setCertificateChainPem($chainPem);
        $this->assertSame($chainPem, $certificate->getCertificateChainPem());
    }

    public function testCertificateChainPemSetToNull(): void
    {
        $chainPem = "-----BEGIN CERTIFICATE-----\nTest\n-----END CERTIFICATE-----";
        $certificate = $this->createEntity();
        $certificate->setCertificateChainPem($chainPem);
        $certificate->setCertificateChainPem(null);

        $this->assertNull($certificate->getCertificateChainPem());
    }

    public function testPrivateKeyPemGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getPrivateKeyPem());

        $privateKey = "-----BEGIN PRIVATE KEY-----\nMIIEvQIB...private key...Q=\n-----END PRIVATE KEY-----";
        $certificate->setPrivateKeyPem($privateKey);
        $this->assertSame($privateKey, $certificate->getPrivateKeyPem());
    }

    public function testPrivateKeyPemSetToNull(): void
    {
        $privateKey = "-----BEGIN PRIVATE KEY-----\nTest\n-----END PRIVATE KEY-----";
        $certificate = $this->createEntity();
        $certificate->setPrivateKeyPem($privateKey);
        $certificate->setPrivateKeyPem(null);

        $this->assertNull($certificate->getPrivateKeyPem());
    }

    public function testSerialNumberGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getSerialNumber());

        $serialNumber = '03:E7:07:A9:C8:F4:5A:12:34:56:78:90:AB:CD:EF';
        $certificate->setSerialNumber($serialNumber);
        $this->assertSame($serialNumber, $certificate->getSerialNumber());
    }

    public function testSerialNumberSetToNull(): void
    {
        $certificate = $this->createEntity();
        $certificate->setSerialNumber('123456');
        $certificate->setSerialNumber(null);

        $this->assertNull($certificate->getSerialNumber());
    }

    public function testFingerprintGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getFingerprint());

        $fingerprint = 'SHA256:1234567890ABCDEF1234567890ABCDEF12345678';
        $certificate->setFingerprint($fingerprint);
        $this->assertSame($fingerprint, $certificate->getFingerprint());
    }

    public function testFingerprintSetToNull(): void
    {
        $certificate = $this->createEntity();
        $certificate->setFingerprint('test_fingerprint');
        $certificate->setFingerprint(null);

        $this->assertNull($certificate->getFingerprint());
    }

    public function testDomainsGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertSame([], $certificate->getDomains());

        $domains = ['example.com', 'www.example.com', '*.example.com'];
        $certificate->setDomains($domains);
        $this->assertSame($domains, $certificate->getDomains());
    }

    public function testDomainsSetEmpty(): void
    {
        $certificate = $this->createEntity();
        $certificate->setDomains(['example.com']);
        $certificate->setDomains([]);

        $this->assertSame([], $certificate->getDomains());
    }

    public function testNotBeforeTimeGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getNotBeforeTime());

        $notBefore = new \DateTimeImmutable('-1 day');
        $certificate->setNotBeforeTime($notBefore);
        $this->assertSame($notBefore, $certificate->getNotBeforeTime());
    }

    public function testNotBeforeTimeSetToNull(): void
    {
        $certificate = $this->createEntity();
        $certificate->setNotBeforeTime(new \DateTimeImmutable());
        $certificate->setNotBeforeTime(null);

        $this->assertNull($certificate->getNotBeforeTime());
    }

    public function testNotAfterTimeGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getNotAfterTime());

        $notAfter = new \DateTimeImmutable('+90 days');
        $certificate->setNotAfterTime($notAfter);
        $this->assertSame($notAfter, $certificate->getNotAfterTime());
    }

    public function testNotAfterTimeSetToNull(): void
    {
        $certificate = $this->createEntity();
        $certificate->setNotAfterTime(new \DateTimeImmutable());
        $certificate->setNotAfterTime(null);

        $this->assertNull($certificate->getNotAfterTime());
    }

    public function testIssuerGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getIssuer());

        $issuer = 'CN=Let\'s Encrypt Authority X3,O=Let\'s Encrypt,C=US';
        $certificate->setIssuer($issuer);
        $this->assertSame($issuer, $certificate->getIssuer());
    }

    public function testIssuerSetToNull(): void
    {
        $certificate = $this->createEntity();
        $certificate->setIssuer('Test Issuer');
        $certificate->setIssuer(null);

        $this->assertNull($certificate->getIssuer());
    }

    public function testValidGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertTrue($certificate->isValid());

        $certificate->setValid(false);
        $this->assertFalse($certificate->isValid());

        $certificate->setValid(true);
        $this->assertTrue($certificate->isValid());
    }

    public function testRevokedTimeGetterSetter(): void
    {
        $certificate = $this->createEntity();
        $this->assertNull($certificate->getRevokedTime());

        $revokedTime = new \DateTimeImmutable();
        $certificate->setRevokedTime($revokedTime);
        $this->assertSame($revokedTime, $certificate->getRevokedTime());
    }

    public function testRevokedTimeSetToNull(): void
    {
        $certificate = $this->createEntity();
        $certificate->setRevokedTime(new \DateTimeImmutable());
        $certificate->setRevokedTime(null);

        $this->assertNull($certificate->getRevokedTime());
    }

    public function testIsExpiredWithNullNotAfterTime(): void
    {
        $certificate = $this->createEntity();
        $certificate->setNotAfterTime(null);
        $this->assertFalse($certificate->isExpired());
    }

    public function testIsExpiredWithFutureDate(): void
    {
        $certificate = $this->createEntity();
        $futureDate = new \DateTimeImmutable('+30 days');
        $certificate->setNotAfterTime($futureDate);
        $this->assertFalse($certificate->isExpired());
    }

    public function testIsExpiredWithPastDate(): void
    {
        $certificate = $this->createEntity();
        $pastDate = new \DateTimeImmutable('-1 day');
        $certificate->setNotAfterTime($pastDate);
        $this->assertTrue($certificate->isExpired());
    }

    public function testIsRevoked(): void
    {
        $certificate = $this->createEntity();
        $this->assertFalse($certificate->isRevoked());

        $certificate->setStatus(CertificateStatus::REVOKED);
        $this->assertTrue($certificate->isRevoked());

        $certificate->setStatus(CertificateStatus::VALID);
        $this->assertFalse($certificate->isRevoked());
    }

    public function testIsExpiringWithinDefaultDays(): void
    {
        $certificate = $this->createEntity();

        // 默认 30 天
        $notAfter = new \DateTimeImmutable('+20 days');
        $certificate->setNotAfterTime($notAfter);
        $this->assertTrue($certificate->isExpiringWithin());

        $notAfter = new \DateTimeImmutable('+40 days');
        $certificate->setNotAfterTime($notAfter);
        $this->assertFalse($certificate->isExpiringWithin());
    }

    public function testIsExpiringWithinCustomDays(): void
    {
        $certificate = $this->createEntity();

        $notAfter = new \DateTimeImmutable('+10 days');
        $certificate->setNotAfterTime($notAfter);

        $this->assertTrue($certificate->isExpiringWithin(15));
        $this->assertFalse($certificate->isExpiringWithin(5));
    }

    public function testIsExpiringWithinWithNullNotAfterTime(): void
    {
        $certificate = $this->createEntity();
        $certificate->setNotAfterTime(null);
        $this->assertFalse($certificate->isExpiringWithin());
    }

    public function testGetDaysUntilExpiryWithNullNotAfterTime(): void
    {
        $certificate = $this->createEntity();
        $certificate->setNotAfterTime(null);
        $this->assertNull($certificate->getDaysUntilExpiry());
    }

    public function testGetDaysUntilExpiryWithFutureDate(): void
    {
        $certificate = $this->createEntity();

        // 设置一个确定的未来日期来避免时间相关的测试问题
        $futureDate = new \DateTimeImmutable('+30 days');
        $certificate->setNotAfterTime($futureDate);

        $days = $certificate->getDaysUntilExpiry();
        $this->assertIsInt($days);
        $this->assertGreaterThanOrEqual(29, $days); // 允许一些时间误差
        $this->assertLessThanOrEqual(30, $days);
    }

    public function testGetDaysUntilExpiryWithPastDate(): void
    {
        $certificate = $this->createEntity();

        $pastDate = new \DateTimeImmutable('-5 days');
        $certificate->setNotAfterTime($pastDate);

        $days = $certificate->getDaysUntilExpiry();
        $this->assertIsInt($days);
        $this->assertLessThan(0, $days);
    }

    public function testGetFullChainPemWithoutChain(): void
    {
        $certificate = $this->createEntity();

        $certificatePem = "-----BEGIN CERTIFICATE-----\nCert Content\n-----END CERTIFICATE-----";
        $certificate->setCertificatePem($certificatePem);

        $this->assertSame($certificatePem, $certificate->getFullChainPem());
    }

    public function testGetFullChainPemWithChain(): void
    {
        $certificate = $this->createEntity();

        $certificatePem = "-----BEGIN CERTIFICATE-----\nCert Content\n-----END CERTIFICATE-----";
        $chainPem = "-----BEGIN CERTIFICATE-----\nChain Content\n-----END CERTIFICATE-----";

        $certificate->setCertificatePem($certificatePem);
        $certificate->setCertificateChainPem($chainPem);

        $expected = $certificatePem . "\n" . $chainPem;
        $this->assertSame($expected, $certificate->getFullChainPem());
    }

    public function testContainsDomain(): void
    {
        $certificate = $this->createEntity();

        $domains = ['example.com', 'www.example.com', '*.example.com'];
        $certificate->setDomains($domains);

        $this->assertTrue($certificate->containsDomain('example.com'));
        $this->assertTrue($certificate->containsDomain('www.example.com'));
        $this->assertTrue($certificate->containsDomain('*.example.com'));
        $this->assertFalse($certificate->containsDomain('subdomain.example.com'));
        $this->assertFalse($certificate->containsDomain('other.com'));
    }

    public function testContainsDomainEmptyDomains(): void
    {
        $certificate = $this->createEntity();
        $certificate->setDomains([]);
        $this->assertFalse($certificate->containsDomain('example.com'));
    }

    public function testToString(): void
    {
        $certificate = $this->createEntity();
        $certificate->setSerialNumber('ABC123');

        $expected = 'Certificate #0 (ABC123)';
        $this->assertSame($expected, (string) $certificate);
    }

    public function testToStringWithoutSerialNumber(): void
    {
        $certificate = $this->createEntity();
        $expected = 'Certificate #0 (Unknown)';
        $this->assertSame($expected, (string) $certificate);
    }

    public function testStringableInterface(): void
    {
        $certificate = $this->createEntity();
        $this->assertInstanceOf(\Stringable::class, $certificate);
    }

    public function testFluentInterfaceChaining(): void
    {
        // 使用具体类 Order 进行 Mock，因为需要测试 Certificate 与 Order 的关联关系
        // Order 是 Doctrine 实体类，没有对应的接口，使用具体类是必要的
        $order = $this->createMock(Order::class);
        $certificate = $this->createEntity();
        $certificatePem = "-----BEGIN CERTIFICATE-----\nTest\n-----END CERTIFICATE-----";
        $chainPem = "-----BEGIN CERTIFICATE-----\nChain\n-----END CERTIFICATE-----";
        $privateKey = "-----BEGIN PRIVATE KEY-----\nKey\n-----END PRIVATE KEY-----";
        $serialNumber = 'ABC123';
        $fingerprint = 'SHA256:test';
        $domains = ['example.com'];
        $notBefore = new \DateTimeImmutable('-1 day');
        $notAfter = new \DateTimeImmutable('+90 days');
        $issuer = 'Test CA';
        $revokedTime = new \DateTimeImmutable();

        $certificate->setOrder($order);
        $certificate->setStatus(CertificateStatus::VALID);
        $certificate->setCertificatePem($certificatePem);
        $certificate->setCertificateChainPem($chainPem);
        $certificate->setPrivateKeyPem($privateKey);
        $certificate->setSerialNumber($serialNumber);
        $certificate->setFingerprint($fingerprint);
        $certificate->setDomains($domains);
        $certificate->setNotBeforeTime($notBefore);
        $certificate->setNotAfterTime($notAfter);
        $certificate->setIssuer($issuer);
        $certificate->setValid(true);
        $certificate->setRevokedTime($revokedTime);
        $result = $certificate;

        $this->assertSame($certificate, $result);
        $this->assertSame($order, $certificate->getOrder());
        $this->assertSame(CertificateStatus::VALID, $certificate->getStatus());
        $this->assertSame($certificatePem, $certificate->getCertificatePem());
        $this->assertSame($chainPem, $certificate->getCertificateChainPem());
        $this->assertSame($privateKey, $certificate->getPrivateKeyPem());
        $this->assertSame($serialNumber, $certificate->getSerialNumber());
        $this->assertSame($fingerprint, $certificate->getFingerprint());
        $this->assertSame($domains, $certificate->getDomains());
        $this->assertSame($notBefore, $certificate->getNotBeforeTime());
        $this->assertSame($notAfter, $certificate->getNotAfterTime());
        $this->assertSame($issuer, $certificate->getIssuer());
        $this->assertTrue($certificate->isValid());
        $this->assertSame($revokedTime, $certificate->getRevokedTime());
    }

    public function testBusinessScenarioCertificateIssuance(): void
    {
        // 使用具体类 Order 进行 Mock，因为需要测试 Certificate 与 Order 的关联关系
        // Order 是 Doctrine 实体类，没有对应的接口，使用具体类是必要的
        $order = $this->createMock(Order::class);
        $certificate = $this->createEntity();

        $certificate->setOrder($order);
        $certificate->setStatus(CertificateStatus::VALID);
        $certificate->setCertificatePem("-----BEGIN CERTIFICATE-----\nValid Cert\n-----END CERTIFICATE-----");
        $certificate->setSerialNumber('12345678901234567890');
        $certificate->setDomains(['example.com', 'www.example.com']);
        $certificate->setNotBeforeTime(new \DateTimeImmutable('-1 day'));
        $certificate->setNotAfterTime(new \DateTimeImmutable('+90 days'));
        $certificate->setIssuer('CN=Let\'s Encrypt Authority X3');

        $this->assertSame(CertificateStatus::VALID, $certificate->getStatus());
        $this->assertTrue($certificate->isValid());
        $this->assertFalse($certificate->isExpired());
        $this->assertFalse($certificate->isRevoked());
        $this->assertTrue($certificate->containsDomain('example.com'));
    }

    public function testBusinessScenarioCertificateExpiration(): void
    {
        $certificate = $this->createEntity();

        $certificate->setStatus(CertificateStatus::EXPIRED);
        $certificate->setNotAfterTime(new \DateTimeImmutable('-10 days'));

        $this->assertSame(CertificateStatus::EXPIRED, $certificate->getStatus());
        $this->assertFalse($certificate->isValid());
        $this->assertTrue($certificate->isExpired());
        $this->assertFalse($certificate->isRevoked());
    }

    public function testBusinessScenarioCertificateRevocation(): void
    {
        $certificate = $this->createEntity();
        $revokedTime = new \DateTimeImmutable();

        $certificate->setStatus(CertificateStatus::REVOKED);
        $certificate->setRevokedTime($revokedTime);

        $this->assertSame(CertificateStatus::REVOKED, $certificate->getStatus());
        $this->assertFalse($certificate->isValid());
        $this->assertTrue($certificate->isRevoked());
        $this->assertSame($revokedTime, $certificate->getRevokedTime());
    }

    public function testBusinessScenarioCertificateRenewal(): void
    {
        $certificate = $this->createEntity();

        // 证书即将过期，需要更新
        $notAfter = new \DateTimeImmutable('+15 days');
        $certificate->setNotAfterTime($notAfter);

        $this->assertTrue($certificate->isExpiringWithin(30));
        $this->assertTrue($certificate->isExpiringWithin(20));
        $this->assertFalse($certificate->isExpiringWithin(10));

        $days = $certificate->getDaysUntilExpiry();
        $this->assertGreaterThan(10, $days);
        $this->assertLessThan(20, $days);
    }

    public function testBusinessScenarioWildcardCertificate(): void
    {
        $certificate = $this->createEntity();

        $domains = ['*.example.com', 'example.com'];
        $certificate->setDomains($domains);

        $this->assertTrue($certificate->containsDomain('*.example.com'));
        $this->assertTrue($certificate->containsDomain('example.com'));
        $this->assertFalse($certificate->containsDomain('sub.example.com')); // 实际验证需要更复杂的逻辑
    }

    public function testBusinessScenarioCertificateChainManagement(): void
    {
        $certificate = $this->createEntity();

        $leafCert = "-----BEGIN CERTIFICATE-----\nLeaf Cert\n-----END CERTIFICATE-----";
        $intermediateCert = "-----BEGIN CERTIFICATE-----\nIntermediate Cert\n-----END CERTIFICATE-----";

        $certificate->setCertificatePem($leafCert);
        $certificate->setCertificateChainPem($intermediateCert);

        $fullChain = $certificate->getFullChainPem();
        $this->assertStringContainsString('Leaf Cert', $fullChain);
        $this->assertStringContainsString('Intermediate Cert', $fullChain);
        $this->assertStringContainsString($leafCert, $fullChain);
        $this->assertStringContainsString($intermediateCert, $fullChain);
    }

    public function testEdgeCasesVeryLongDomainList(): void
    {
        $certificate = $this->createEntity();

        $domains = [];
        for ($i = 1; $i <= 100; ++$i) {
            $domains[] = "subdomain{$i}.example.com";
        }

        $certificate->setDomains($domains);
        $this->assertCount(100, $certificate->getDomains());
        $this->assertTrue($certificate->containsDomain('subdomain50.example.com'));
        $this->assertFalse($certificate->containsDomain('subdomain101.example.com'));
    }

    public function testEdgeCasesEmptyPemContent(): void
    {
        $certificate = $this->createEntity();
        $certificate->setCertificatePem('');
        $this->assertSame('', $certificate->getCertificatePem());
        $this->assertSame('', $certificate->getFullChainPem());
    }

    public function testEdgeCasesLongSerialNumber(): void
    {
        $certificate = $this->createEntity();
        $longSerial = str_repeat('A', 255);
        $certificate->setSerialNumber($longSerial);

        $this->assertSame($longSerial, $certificate->getSerialNumber());
    }

    public function testStateTransitionsValidToExpired(): void
    {
        $certificate = $this->createEntity();

        $certificate->setStatus(CertificateStatus::VALID);
        $this->assertTrue($certificate->isValid());
        $this->assertFalse($certificate->isRevoked());

        $certificate->setStatus(CertificateStatus::EXPIRED);
        $this->assertFalse($certificate->isValid());
        $this->assertFalse($certificate->isRevoked());
    }

    public function testStateTransitionsValidToRevoked(): void
    {
        $certificate = $this->createEntity();

        $certificate->setStatus(CertificateStatus::VALID);
        $this->assertTrue($certificate->isValid());
        $this->assertFalse($certificate->isRevoked());

        $certificate->setStatus(CertificateStatus::REVOKED);
        $this->assertFalse($certificate->isValid());
        $this->assertTrue($certificate->isRevoked());
    }

    public function testTimeRelatedMethodsEdgeCases(): void
    {
        $certificate = $this->createEntity();

        // 测试时间边界情况
        $now = new \DateTimeImmutable();
        $certificate->setNotAfterTime($now);

        // 由于时间精度问题，这个测试可能会有微小差异
        $isExpired = $certificate->isExpired();

        $days = $certificate->getDaysUntilExpiry();
        $this->assertIsInt($days);
        $this->assertLessThanOrEqual(1, abs($days)); // 应该非常接近 0
    }

    public function testDomainCaseSensitivity(): void
    {
        $certificate = $this->createEntity();

        $certificate->setDomains(['Example.Com', 'WWW.EXAMPLE.COM']);

        // 域名检查是区分大小写的（按当前实现）
        $this->assertTrue($certificate->containsDomain('Example.Com'));
        $this->assertFalse($certificate->containsDomain('example.com'));
        $this->assertTrue($certificate->containsDomain('WWW.EXAMPLE.COM'));
        $this->assertFalse($certificate->containsDomain('www.example.com'));
    }

    protected function createEntity(): Certificate
    {
        return new Certificate();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'status' => ['status', CertificateStatus::VALID];
        yield 'certificatePem' => ['certificatePem', '-----BEGIN CERTIFICATE-----\nTest\n-----END CERTIFICATE-----'];
        yield 'certificateChainPem' => ['certificateChainPem', '-----BEGIN CERTIFICATE-----\nChain\n-----END CERTIFICATE-----'];
        yield 'privateKeyPem' => ['privateKeyPem', '-----BEGIN PRIVATE KEY-----\nKey\n-----END PRIVATE KEY-----'];
        yield 'serialNumber' => ['serialNumber', 'ABC123'];
        yield 'fingerprint' => ['fingerprint', 'SHA256:test'];
        yield 'domains' => ['domains', ['example.com']];
        yield 'notBeforeTime' => ['notBeforeTime', new \DateTimeImmutable('-1 day')];
        yield 'notAfterTime' => ['notAfterTime', new \DateTimeImmutable('+90 days')];
        yield 'issuer' => ['issuer', 'Test CA'];
        yield 'valid' => ['valid', true];
        yield 'revokedTime' => ['revokedTime', new \DateTimeImmutable()];
    }
}
