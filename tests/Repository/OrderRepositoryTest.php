<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(OrderRepository::class)]
#[RunTestsInSeparateProcesses]
final class OrderRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(OrderRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        $this->assertIsArray($results);
        foreach ($results as $result) {
            $this->assertInstanceOf(Order::class, $result);
        }
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(OrderRepository::class);
        $order = $this->createBasicOrderForTest();

        $repository->save($order);
        $orderId = $order->getId();
        $this->assertNotNull($orderId);

        $repository->remove($order);

        $removedOrder = $repository->find($orderId);
        $this->assertNull($removedOrder);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(OrderRepository::class);

        // Create multiple orders with different URLs for sorting
        $order1 = $this->createBasicOrderForTest();
        $order1->setOrderUrl('https://a.example.com/order');
        $repository->save($order1);

        $order2 = $this->createBasicOrderForTest();
        $order2->setOrderUrl('https://z.example.com/order');
        $repository->save($order2);

        // Test ascending order
        $result = $repository->findOneBy(['status' => OrderStatus::PENDING], ['orderUrl' => 'ASC']);
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['status' => OrderStatus::PENDING], ['orderUrl' => 'DESC']);
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($order2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(OrderRepository::class);
        $order = $this->createBasicOrderForTest();

        $repository->save($order);
        $orderId = $order->getId();
        $this->assertNotNull($orderId);

        // Verify entity exists
        $foundEntity = $repository->find($orderId);
        $this->assertInstanceOf(Order::class, $foundEntity);

        // Remove entity
        $repository->remove($order);

        // Verify entity no longer exists
        $removedEntity = $repository->find($orderId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithAccountAssociation(): void
    {
        $repository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create order with account
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://assoc-test.example.com/order');
        $repository->save($order);

        $results = $repository->findBy(['account' => $account]);
        // Remove redundant assertIsArray as PHPDoc already specifies list<Order>
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Order::class, $result);
            if ($result->getId() === $order->getId()) {
                $found = true;
                $account = $result->getAccount();
                $this->assertNotNull($account);
                $this->assertSame($account->getId(), $account->getId());
            }
        }
        $this->assertTrue($found, 'Order with account association should be found');
    }

    public function testCountWithAccountAssociation(): void
    {
        $repository = self::getService(OrderRepository::class);
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        $initialCount = $repository->count(['account' => $account]);

        // Create order with account
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://count-assoc-test.example.com/order');
        $repository->save($order);

        $newCount = $repository->count(['account' => $account]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testFindByWithNullExpiresTime(): void
    {
        $repository = self::getService(OrderRepository::class);
        $order = $this->createBasicOrderForTest();
        // expiresTime is null by default

        $repository->save($order);

        $results = $repository->findBy(['expiresTime' => null]);
        // Remove redundant assertIsArray as PHPDoc already specifies list<Order>
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Order::class, $result);
            if ($result->getId() === $order->getId()) {
                $found = true;
                $this->assertNull($result->getExpiresTime());
            }
        }
        $this->assertTrue($found, 'Order with null expiresTime should be found');
    }

    public function testCountWithNullExpiresTime(): void
    {
        $repository = self::getService(OrderRepository::class);
        $initialCount = $repository->count(['expiresTime' => null]);

        $order = $this->createBasicOrderForTest();
        // expiresTime is null by default

        $repository->save($order);

        $newCount = $repository->count(['expiresTime' => null]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testFindByWithNullError(): void
    {
        $repository = self::getService(OrderRepository::class);
        $order = $this->createBasicOrderForTest();
        // error is null by default

        $repository->save($order);

        $results = $repository->findBy(['error' => null]);
        // Remove redundant assertIsArray as PHPDoc already specifies list<Order>
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Order::class, $result);
            if ($result->getId() === $order->getId()) {
                $found = true;
                $this->assertNull($result->getError());
            }
        }
        $this->assertTrue($found, 'Order with null error should be found');
    }

    public function testCountWithNullError(): void
    {
        $repository = self::getService(OrderRepository::class);
        $initialCount = $repository->count(['error' => null]);

        $order = $this->createBasicOrderForTest();
        // error is null by default

        $repository->save($order);

        $newCount = $repository->count(['error' => null]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    private function createBasicOrderForTest(): Order
    {
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
        $order->setOrderUrl('https://test.example.com/order');

        return $order;
    }

    protected function createNewEntity(): object
    {
        $accountRepository = self::getService(AccountRepository::class);

        // Create account first
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $accountRepository->save($account);

        // Create order
        $entity = new Order();
        $entity->setAccount($account);
        $entity->setStatus(OrderStatus::PENDING);
        $entity->setOrderUrl('https://test.example.com/order-' . uniqid());

        return $entity;
    }

    /**
     * @return OrderRepository
     */
    protected function getRepository(): OrderRepository
    {
        return self::getService(OrderRepository::class);
    }
}
