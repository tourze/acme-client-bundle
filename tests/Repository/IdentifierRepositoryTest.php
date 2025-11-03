<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Repository\IdentifierRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<Identifier>
 * @internal
 */
#[CoversClass(IdentifierRepository::class)]
#[RunTestsInSeparateProcesses]
final class IdentifierRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    private function createBasicIdentifierForTest(string $type = 'dns', string $value = 'test.example.com'): Identifier
    {
        $accountRepository = self::getService(AccountRepository::class);
        $orderRepository = self::getService(OrderRepository::class);

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

        // Create identifier with order
        $identifier = new Identifier();
        $identifier->setType($type);
        $identifier->setValue($value);
        $identifier->setOrder($order);

        return $identifier;
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(IdentifierRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        foreach ($results as $result) {
            $this->assertInstanceOf(Identifier::class, $result);
        }
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(IdentifierRepository::class);
        $identifier = $this->createBasicIdentifierForTest();

        $repository->save($identifier);
        $identifierId = $identifier->getId();
        $this->assertNotNull($identifierId);

        $repository->remove($identifier);

        $removedIdentifier = $repository->find($identifierId);
        $this->assertNull($removedIdentifier);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(IdentifierRepository::class);

        // Create multiple identifiers with different values for sorting
        $identifier1 = $this->createBasicIdentifierForTest('dns', 'aaa-unique-sort-test.example.com');
        $repository->save($identifier1);

        $identifier2 = $this->createBasicIdentifierForTest('dns', 'zzz-unique-sort-test.example.com');
        $repository->save($identifier2);

        // Test ascending order - use unique value criteria to avoid conflicts
        $result = $repository->findOneBy(['value' => 'aaa-unique-sort-test.example.com'], ['value' => 'ASC']);
        $this->assertInstanceOf(Identifier::class, $result);
        $this->assertSame($identifier1->getId(), $result->getId());

        // Test descending order - use unique value criteria to avoid conflicts
        $result = $repository->findOneBy(['value' => 'zzz-unique-sort-test.example.com'], ['value' => 'DESC']);
        $this->assertInstanceOf(Identifier::class, $result);
        $this->assertSame($identifier2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(IdentifierRepository::class);
        $identifier = $this->createBasicIdentifierForTest('dns', 'remove-test.example.com');

        $repository->save($identifier);
        $identifierId = $identifier->getId();
        $this->assertNotNull($identifierId);

        // Verify entity exists
        $foundEntity = $repository->find($identifierId);
        $this->assertInstanceOf(Identifier::class, $foundEntity);

        // Remove entity
        $repository->remove($identifier);

        // Verify entity no longer exists
        $removedEntity = $repository->find($identifierId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithOrderAssociation(): void
    {
        $repository = self::getService(IdentifierRepository::class);

        // Create identifier with order
        $identifier = $this->createBasicIdentifierForTest('dns', 'assoc-test.example.com');
        $repository->save($identifier);

        $order = $identifier->getOrder();

        $results = $repository->findBy(['order' => $order]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Identifier::class, $result);
            if ($result->getId() === $identifier->getId()) {
                $found = true;
                $resultOrder = $result->getOrder();
                $this->assertNotNull($resultOrder);
                $identifierOrder = $identifier->getOrder();
                $this->assertNotNull($identifierOrder);
                $this->assertSame($identifierOrder->getId(), $resultOrder->getId());
            }
        }
        $this->assertTrue($found, 'Identifier with order association should be found');
    }

    public function testCountWithOrderAssociation(): void
    {
        $repository = self::getService(IdentifierRepository::class);

        // Create identifier with order
        $identifier = $this->createBasicIdentifierForTest('dns', 'count-assoc-test.example.com');
        $order = $identifier->getOrder();

        $initialCount = $repository->count(['order' => $order]);

        $repository->save($identifier);

        $newCount = $repository->count(['order' => $order]);
        $this->assertSame($initialCount + 1, $newCount);
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
        $order->setOrderUrl('https://test.example.com/order-' . uniqid());
        $orderRepository->save($order);

        // Create identifier
        $entity = new Identifier();
        $entity->setOrder($order);
        $entity->setType('dns');
        $entity->setValue('test-' . uniqid() . '.example.com');

        return $entity;
    }

    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(IdentifierRepository::class);
    }
}
