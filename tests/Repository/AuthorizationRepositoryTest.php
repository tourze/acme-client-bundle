<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;
use Tourze\ACMEClientBundle\Repository\IdentifierRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<Authorization>
 * @internal
 */
#[CoversClass(AuthorizationRepository::class)]
#[RunTestsInSeparateProcesses]
final class AuthorizationRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    private function createBasicAuthorizationForTest(): Authorization
    {
        // 创建 Account 实体
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');

        // 创建 Order 实体并关联 Account
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://test-order.example.com');

        // 创建 Identifier 实体并关联 Order
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('test.example.com');
        $identifier->setOrder($order);

        // 创建 Authorization 实体并关联其他实体
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://test.example.com/auth');
        $authorization->setIdentifier($identifier);
        $authorization->setOrder($order);

        return $authorization;
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        foreach ($results as $result) {
            $this->assertInstanceOf(Authorization::class, $result);
        }
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
        $authorization = $this->createBasicAuthorizationForTest();

        $repository->save($authorization);
        $authorizationId = $authorization->getId();
        $this->assertNotNull($authorizationId);

        $repository->remove($authorization);

        $removedAuthorization = $repository->find($authorizationId);
        $this->assertNull($removedAuthorization);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(AuthorizationRepository::class);

        // Create multiple authorizations with different URLs for sorting
        $authorization1 = $this->createBasicAuthorizationForTest();
        $authorization1->setAuthorizationUrl('https://a.example.com/auth');
        $repository->save($authorization1);

        $authorization2 = $this->createBasicAuthorizationForTest();
        $authorization2->setAuthorizationUrl('https://z.example.com/auth');
        $repository->save($authorization2);

        // Test ascending order
        $result = $repository->findOneBy(['status' => AuthorizationStatus::PENDING], ['authorizationUrl' => 'ASC']);
        $this->assertInstanceOf(Authorization::class, $result);
        $this->assertSame($authorization1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['status' => AuthorizationStatus::PENDING], ['authorizationUrl' => 'DESC']);
        $this->assertInstanceOf(Authorization::class, $result);
        $this->assertSame($authorization2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
        $authorization = $this->createBasicAuthorizationForTest();

        $repository->save($authorization);
        $authorizationId = $authorization->getId();
        $this->assertNotNull($authorizationId);

        // Verify entity exists
        $foundEntity = $repository->find($authorizationId);
        $this->assertInstanceOf(Authorization::class, $foundEntity);

        // Remove entity
        $repository->remove($authorization);

        // Verify entity no longer exists
        $removedEntity = $repository->find($authorizationId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithOrderAssociation(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
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

        // Create identifier with order
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('assoc-test.example.com');
        $identifier->setOrder($order);
        self::getService(IdentifierRepository::class)->save($identifier);

        // Create authorization with order and identifier
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://assoc-test.example.com/auth');
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $repository->save($authorization);

        $results = $repository->findBy(['order' => $order]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Authorization::class, $result);
            if ($result->getId() === $authorization->getId()) {
                $found = true;
                $resultOrder = $result->getOrder();
                $this->assertNotNull($resultOrder);
                $this->assertSame($order->getId(), $resultOrder->getId());
            }
        }
        $this->assertTrue($found, 'Authorization with order association should be found');
    }

    public function testCountWithOrderAssociation(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
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

        // Create identifier with order
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('count-assoc-test.example.com');
        $identifier->setOrder($order);
        self::getService(IdentifierRepository::class)->save($identifier);

        // Create authorization with order and identifier
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://count-assoc-test.example.com/auth');
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $repository->save($authorization);

        $newCount = $repository->count(['order' => $order]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testFindByWithIdentifierAssociation(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
        $identifierRepository = self::getService(IdentifierRepository::class);
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

        // Create identifier
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('identifier-test.example.com');
        $identifier->setOrder($order);
        $identifierRepository->save($identifier);

        // Create authorization with identifier
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://identifier-assoc-test.example.com/auth');
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $repository->save($authorization);

        $results = $repository->findBy(['identifier' => $identifier]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Authorization::class, $result);
            if ($result->getId() === $authorization->getId()) {
                $found = true;
                $resultIdentifier = $result->getIdentifier();
                $this->assertNotNull($resultIdentifier);
                $this->assertSame($identifier->getId(), $resultIdentifier->getId());
            }
        }
        $this->assertTrue($found, 'Authorization with identifier association should be found');
    }

    public function testCountWithIdentifierAssociation(): void
    {
        $repository = self::getService(AuthorizationRepository::class);
        $identifierRepository = self::getService(IdentifierRepository::class);
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

        // Create identifier
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('count-identifier-test.example.com');
        $identifier->setOrder($order);
        $identifierRepository->save($identifier);

        $initialCount = $repository->count(['identifier' => $identifier]);

        // Create authorization with identifier
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://count-identifier-assoc-test.example.com/auth');
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $repository->save($authorization);

        $newCount = $repository->count(['identifier' => $identifier]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(AuthorizationRepository::class);
    }

    protected function createNewEntity(): Authorization
    {
        // 创建依赖实体
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key-' . uniqid());
        $account->setPublicKeyJwk('{"test": "jwk_' . uniqid() . '"}');

        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://test-order-' . uniqid() . '.example.com');

        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('test-' . uniqid() . '.example.com');
        $identifier->setOrder($order);

        // 创建Authorization实体
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://test-auth-' . uniqid() . '.example.com/auth');
        $authorization->setIdentifier($identifier);
        $authorization->setOrder($order);
        $authorization->setExpiresTime(null);
        $authorization->setWildcard(false);
        $authorization->setValid(false);

        return $authorization;
    }
}
