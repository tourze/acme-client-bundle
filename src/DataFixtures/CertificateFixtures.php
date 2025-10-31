<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;

class CertificateFixtures extends Fixture implements DependentFixtureInterface
{
    public const CERTIFICATE_VALID_REFERENCE = 'certificate-valid';
    public const CERTIFICATE_EXPIRED_REFERENCE = 'certificate-expired';

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $orderValid = $this->getReference(OrderFixtures::ORDER_VALID_REFERENCE, Order::class);
        $orderReady = $this->getReference(OrderFixtures::ORDER_READY_REFERENCE, Order::class);

        // 为有效订单创建证书
        $certificate1 = new Certificate();
        $certificate1->setOrder($orderValid);
        $certificate1->setStatus(CertificateStatus::VALID);
        $certificate1->setCertificatePem("-----BEGIN CERTIFICATE-----\nMIIFXTCCBEWgAwIBAgISA1234567890abcdefghijklmnopqrsCg123\nABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/abcdefghijklmnop\nqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/abcd\nefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123\n-----END CERTIFICATE-----");
        $certificate1->setCertificateChainPem("-----BEGIN CERTIFICATE-----\nMIIEkjCCA3qgAwIBAgIQCgFBQgAAAVOFc2oLheynCDANBgkqhkiG\n9w0BAQsFADBGMQswCQYDVQQGEwJVUzEiMCAGA1UEChMZR29vZ2xl\nVHJ1c3QgU2VydmljZXMgTExDMRMwEQYDVQQDEwpHVFMgQ0EgMVAx\n-----END CERTIFICATE-----");
        $certificate1->setPrivateKeyPem("-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC7\nvfuebgNr918buzUIGlHUOH1IFHkWP9ENOd23LnOdNtTtm3KTJRHA\nABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/abcdefghijklmn\n-----END PRIVATE KEY-----");
        $certificate1->setSerialNumber('01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef:01:23:45:67');
        $certificate1->setFingerprint('sha256:1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef');
        $certificate1->setIssuer('CN=Let\'s Encrypt Authority X3,O=Let\'s Encrypt,C=US');
        $certificate1->setNotBeforeTime(new \DateTimeImmutable('-1 day'));
        $certificate1->setNotAfterTime(new \DateTimeImmutable('+89 days'));
        $certificate1->setDomains(['*.prod.acme.tourze.dev', 'prod.acme.tourze.dev']);
        $certificate1->setValid(true);
        $certificate1->setIssuedTime(new \DateTimeImmutable('-1 day'));
        $certificate1->setExpiresTime(new \DateTimeImmutable('+89 days'));
        $certificateData = json_encode([
            'subject' => ['CN' => 'prod.acme.tourze.dev'],
            'issuer' => ['CN' => 'Let\'s Encrypt Authority X3'],
            'valid_from' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'valid_to' => (new \DateTimeImmutable('+89 days'))->format('Y-m-d H:i:s'),
        ]);
        $certificate1->setCertificateData(false !== $certificateData ? $certificateData : null);

        // 为ready订单创建一个过期的证书作为测试数据
        $certificate2 = new Certificate();
        $certificate2->setOrder($orderReady);
        $certificate2->setStatus(CertificateStatus::EXPIRED);
        $certificate2->setCertificatePem("-----BEGIN CERTIFICATE-----\nMIIFXTCCBEWgAwIBAgISA9876543210zyxwvutsrqponmlkjih\nFEDCBA9876543210+/zyxwvutsrqponmlkjihgfedcbaZYXWVU\nTSRQPONMLKJIHGFEDCBA9876543210+/zyxwvutsrqponmlkji\nhgfedcbaZYXWVUTSRQPONMLKJIHGFEDCBA9876543210+/zyxw\n-----END CERTIFICATE-----");
        $certificate2->setDomains(['old.acme.tourze.dev']);
        $certificate2->setNotBeforeTime(new \DateTimeImmutable('-100 days'));
        $certificate2->setNotAfterTime(new \DateTimeImmutable('-10 days'));
        $certificate2->setSerialNumber('fe:dc:ba:98:76:54:32:10:fe:dc:ba:98:76:54:32:10:fe:dc:ba:98');
        $certificate2->setValid(false);
        $certificate2->setIssuedTime(new \DateTimeImmutable('-100 days'));
        $certificate2->setExpiresTime(new \DateTimeImmutable('-10 days'));

        $manager->persist($certificate1);
        $manager->persist($certificate2);
        $manager->flush();

        // 保存引用供其他 Fixtures 使用
        $this->addReference(self::CERTIFICATE_VALID_REFERENCE, $certificate1);
        $this->addReference(self::CERTIFICATE_EXPIRED_REFERENCE, $certificate2);
    }
}
