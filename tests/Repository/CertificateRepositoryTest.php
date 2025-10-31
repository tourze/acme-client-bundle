<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Certificate;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\CertificateStatus;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Repository\CertificateRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CertificateRepository::class)]
#[RunTestsInSeparateProcesses]
final class CertificateRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        $this->assertIsArray($results);
        foreach ($results as $result) {
            $this->assertInstanceOf(Certificate::class, $result);
        }
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $certificate = $this->createBasicCertificateForTest();
        $certificate->setSerialNumber('save_remove_12345');

        $repository->save($certificate);
        $certificateId = $certificate->getId();
        $this->assertNotNull($certificateId);

        $repository->remove($certificate);

        $removedCertificate = $repository->find($certificateId);
        $this->assertNull($removedCertificate);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $orderRepository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create first order for first certificate
        $order1 = new Order();
        $order1->setAccount($account);
        $order1->setStatus(OrderStatus::PENDING);
        $order1->setOrderUrl('https://order1.example.com/order');
        $orderRepository->save($order1);

        // Create second order for second certificate
        $order2 = new Order();
        $order2->setAccount($account);
        $order2->setStatus(OrderStatus::PENDING);
        $order2->setOrderUrl('https://order2.example.com/order');
        $orderRepository->save($order2);

        // Create multiple certificates with different serial numbers for sorting
        $certificate1 = $this->createBasicCertificateForTest();
        $certificate1->setOrder($order1);
        $certificate1->setStatus(CertificateStatus::ISSUED);
        $certificate1->setSerialNumber('A123');
        $repository->save($certificate1);

        $certificate2 = $this->createBasicCertificateForTest();
        $certificate2->setOrder($order2);
        $certificate2->setStatus(CertificateStatus::ISSUED);
        $certificate2->setSerialNumber('Z789');
        $repository->save($certificate2);

        // Test ascending order
        $result = $repository->findOneBy(['status' => CertificateStatus::ISSUED], ['serialNumber' => 'ASC']);
        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertSame($certificate1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['status' => CertificateStatus::ISSUED], ['serialNumber' => 'DESC']);
        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertSame($certificate2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $certificate = $this->createBasicCertificateForTest();

        $repository->save($certificate);
        $certificateId = $certificate->getId();
        $this->assertNotNull($certificateId);

        // Verify entity exists
        $foundEntity = $repository->find($certificateId);
        $this->assertInstanceOf(Certificate::class, $foundEntity);

        // Remove entity
        $repository->remove($certificate);

        // Verify entity no longer exists
        $removedEntity = $repository->find($certificateId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithOrderAssociation(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $orderRepository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create order
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://order.example.com/order');
        $orderRepository->save($order);

        // Create certificate with order
        $certificate = $this->createBasicCertificateForTest();
        $certificate->setOrder($order);
        $certificate->setStatus(CertificateStatus::ISSUED);
        $repository->save($certificate);

        $results = $repository->findBy(['order' => $order]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Certificate::class, $result);
            if ($result->getId() === $certificate->getId()) {
                $found = true;
                $order = $result->getOrder();
                $this->assertNotNull($order);
                $this->assertSame($order->getId(), $order->getId());
            }
        }
        $this->assertTrue($found, 'Certificate with order association should be found');
    }

    public function testCountWithOrderAssociation(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $orderRepository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create order
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://order.example.com/order');
        $orderRepository->save($order);

        $initialCount = $repository->count(['order' => $order]);

        // Create certificate with order
        $certificate = $this->createBasicCertificateForTest();
        $certificate->setOrder($order);
        $certificate->setStatus(CertificateStatus::ISSUED);
        $repository->save($certificate);

        $newCount = $repository->count(['order' => $order]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testFindByWithNullSerialNumber(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $certificate = $this->createBasicCertificateForTest();
        $certificate->setSerialNumber(null); // explicitly set to null

        $repository->save($certificate);

        $results = $repository->findBy(['serialNumber' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Certificate::class, $result);
            if ($result->getId() === $certificate->getId()) {
                $found = true;
                $this->assertNull($result->getSerialNumber());
            }
        }
        $this->assertTrue($found, 'Certificate with null serialNumber should be found');
    }

    public function testCountWithNullSerialNumber(): void
    {
        $repository = self::getService(CertificateRepository::class);
        $initialCount = $repository->count(['serialNumber' => null]);

        $certificate = $this->createBasicCertificateForTest();
        $certificate->setSerialNumber(null); // explicitly set to null

        $repository->save($certificate);

        $newCount = $repository->count(['serialNumber' => null]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    private function createBasicCertificateForTest(): Certificate
    {
        $orderRepository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create order
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://order.example.com/order');
        $orderRepository->save($order);

        // Create certificate
        $certificate = new Certificate();
        $certificate->setOrder($order);
        $certificate->setStatus(CertificateStatus::ISSUED);
        $certificate->setCertificatePem('-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----');
        $certificate->setSerialNumber('12345');

        return $certificate;
    }

    protected function createNewEntity(): object
    {
        $orderRepository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create order
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://order.example.com/order');
        $orderRepository->save($order);

        // Create certificate
        $entity = new Certificate();
        $entity->setOrder($order);
        $entity->setStatus(CertificateStatus::ISSUED);
        $entity->setCertificatePem('-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----');
        $entity->setSerialNumber('test-cert-' . uniqid());

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<Certificate>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(CertificateRepository::class);
    }
}
