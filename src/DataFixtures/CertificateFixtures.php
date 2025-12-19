<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;

#[When(env: 'test')]
#[When(env: 'dev')]
class CertificateFixtures extends Fixture implements DependentFixtureInterface
{
    public const CERTIFICATE_REFERENCE = 'certificate';

    public function load(ObjectManager $manager): void
    {
        $certificate = new Certificate();
        $certificate->setOrder($this->getReference(OrderFixtures::ORDER_REFERENCE, Order::class));
        $certificate->setStatus(CertificateStatus::VALID);
        $certificate->setCertificatePem($this->getTestCertificatePem());
        $certificate->setCertificateChainPem($this->getTestCertificateChainPem());
        $certificate->setPrivateKeyPem($this->getTestPrivateKeyPem());
        $certificate->setSerialNumber('0123456789ABCDEF');
        $certificate->setFingerprint('AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD');
        $certificate->setIssuer("Let's Encrypt Authority X3");
        $certificate->setNotBeforeTime(new \DateTimeImmutable('-1 day'));
        $certificate->setNotAfterTime(new \DateTimeImmutable('+89 days'));
        $certificate->setDomains(['test-domain.local', 'www.test-domain.local']); // @phpstan-ignore shipmonk.dataFixturesPlaceholderImageService

        $manager->persist($certificate);
        $this->addReference(self::CERTIFICATE_REFERENCE, $certificate);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
        ];
    }

    private function getTestCertificatePem(): string
    {
        return <<<'PEM'
-----BEGIN CERTIFICATE-----
MIIFtest_certificate_for_fixtures_only_do_not_use_in_production
-----END CERTIFICATE-----
PEM;
    }

    private function getTestCertificateChainPem(): string
    {
        return <<<'PEM'
-----BEGIN CERTIFICATE-----
MIIFtest_certificate_chain_for_fixtures_only_do_not_use_in_production
-----END CERTIFICATE-----
PEM;
    }

    private function getTestPrivateKeyPem(): string
    {
        return <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEtest_private_key_for_fixtures_only_do_not_use_in_production
-----END RSA PRIVATE KEY-----
PEM;
    }
}
