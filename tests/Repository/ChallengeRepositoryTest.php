<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Repository\ChallengeRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<Challenge>
 * @internal
 */
#[CoversClass(ChallengeRepository::class)]
#[RunTestsInSeparateProcesses]
final class ChallengeRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    private function createBasicChallengeForTest(): Challenge
    {
        $entityManager = self::getEntityManager();

        // 创建 Account 实体
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $entityManager->persist($account);

        // 创建 Order 实体并关联 Account
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://test-order.example.com');
        $entityManager->persist($order);

        // 创建 Identifier 实体并关联 Order
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('test.example.com');
        $identifier->setOrder($order);
        $entityManager->persist($identifier);

        // 创建 Authorization 实体并关联其他实体
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://test.example.com/auth');
        $authorization->setIdentifier($identifier);
        $authorization->setOrder($order);
        $entityManager->persist($authorization);

        // 创建 Challenge 实体并关联 Authorization
        $challenge = new Challenge();
        $challenge->setStatus(ChallengeStatus::PENDING);
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setChallengeUrl('https://test.example.com/challenge');
        $challenge->setToken('test-token-' . uniqid());
        $challenge->setKeyAuthorization('test-key-auth-' . uniqid());
        $challenge->setAuthorization($authorization);

        // 需要 flush 以确保所有依赖实体都被保存
        $entityManager->flush();

        return $challenge;
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        foreach ($results as $result) {
            $this->assertInstanceOf(Challenge::class, $result);
        }
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $challenge = $this->createBasicChallengeForTest();

        $repository->save($challenge);
        $challengeId = $challenge->getId();
        $this->assertNotNull($challengeId);

        $repository->remove($challenge);

        $removedChallenge = $repository->find($challengeId);
        $this->assertNull($removedChallenge);
    }

    public function testFindByWithTypeCriteria(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $results = $repository->findBy(['type' => ChallengeType::DNS_01]);
        // Type is guaranteed by repository method signature
        foreach ($results as $result) {
            $this->assertInstanceOf(Challenge::class, $result);
            $this->assertSame(ChallengeType::DNS_01, $result->getType());
        }
    }

    public function testFindByWithStatusCriteria(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $results = $repository->findBy(['status' => ChallengeStatus::PENDING]);
        // Type is guaranteed by repository method signature
        foreach ($results as $result) {
            $this->assertInstanceOf(Challenge::class, $result);
            $this->assertSame(ChallengeStatus::PENDING, $result->getStatus());
        }
    }

    public function testFindOneByWithNullCriteriaShouldReturnNull(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $result = $repository->findOneBy(['token' => null]);
        $this->assertNull($result);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(ChallengeRepository::class);

        // Create multiple challenges with different URLs for sorting
        $challenge1 = $this->createBasicChallengeForTest();
        $challenge1->setChallengeUrl('https://a.example.com/challenge');
        $challenge1->setToken('test-token-1');
        $challenge1->setKeyAuthorization('test-key-auth-1');
        $repository->save($challenge1);

        $challenge2 = $this->createBasicChallengeForTest();
        $challenge2->setChallengeUrl('https://z.example.com/challenge');
        $challenge2->setToken('test-token-2');
        $challenge2->setKeyAuthorization('test-key-auth-2');
        $repository->save($challenge2);

        // Test ascending order
        $result = $repository->findOneBy(['status' => ChallengeStatus::PENDING], ['challengeUrl' => 'ASC']);
        $this->assertInstanceOf(Challenge::class, $result);
        $this->assertSame($challenge1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['status' => ChallengeStatus::PENDING], ['challengeUrl' => 'DESC']);
        $this->assertInstanceOf(Challenge::class, $result);
        $this->assertSame($challenge2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $challenge = $this->createBasicChallengeForTest();
        $challenge->setChallengeUrl('https://remove-test.example.com/challenge');

        $repository->save($challenge);
        $challengeId = $challenge->getId();
        $this->assertNotNull($challengeId);

        // Verify entity exists
        $foundEntity = $repository->find($challengeId);
        $this->assertInstanceOf(Challenge::class, $foundEntity);

        // Remove entity
        $repository->remove($challenge);

        // Verify entity no longer exists
        $removedEntity = $repository->find($challengeId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithDnsRecordNameNullCriteria(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $challenge = $this->createBasicChallengeForTest();
        $challenge->setChallengeUrl('https://null-test.example.com/challenge');
        // dnsRecordName is null by default

        $repository->save($challenge);

        $results = $repository->findBy(['dnsRecordName' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Challenge::class, $result);
            if ($result->getId() === $challenge->getId()) {
                $found = true;
                $this->assertNull($result->getDnsRecordName());
            }
        }
        $this->assertTrue($found, 'Challenge with null dnsRecordName should be found');
    }

    public function testFindByWithDnsRecordValueNullCriteria(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $challenge = $this->createBasicChallengeForTest();
        $challenge->setChallengeUrl('https://null-test.example.com/challenge');
        // dnsRecordValue is null by default

        $repository->save($challenge);

        $results = $repository->findBy(['dnsRecordValue' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Challenge::class, $result);
            if ($result->getId() === $challenge->getId()) {
                $found = true;
                $this->assertNull($result->getDnsRecordValue());
            }
        }
        $this->assertTrue($found, 'Challenge with null dnsRecordValue should be found');
    }

    public function testCountWithDnsRecordNameNullCriteria(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $initialCount = $repository->count(['dnsRecordName' => null]);

        $challenge = $this->createBasicChallengeForTest();
        $challenge->setChallengeUrl('https://count-null-test.example.com/challenge');
        // dnsRecordName is null by default

        $repository->save($challenge);

        $newCount = $repository->count(['dnsRecordName' => null]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testCountWithDnsRecordValueNullCriteria(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $initialCount = $repository->count(['dnsRecordValue' => null]);

        $challenge = $this->createBasicChallengeForTest();
        $challenge->setChallengeUrl('https://count-null-test.example.com/challenge');
        // dnsRecordValue is null by default

        $repository->save($challenge);

        $newCount = $repository->count(['dnsRecordValue' => null]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testFindByWithAuthorizationAssociation(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $challenge = $this->createBasicChallengeForTest();
        $challenge->setChallengeUrl('https://assoc-test.example.com/challenge');
        $authorization = $challenge->getAuthorization();
        $this->assertNotNull($authorization);
        $repository->save($challenge);

        $results = $repository->findBy(['authorization' => $authorization]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Challenge::class, $result);
            if ($result->getId() === $challenge->getId()) {
                $found = true;
                $resultAuthorization = $result->getAuthorization();
                $this->assertNotNull($resultAuthorization);
                $authorizationId = $authorization->getId();
                $this->assertNotNull($authorizationId);
                $this->assertSame($authorizationId, $resultAuthorization->getId());
            }
        }
        $this->assertTrue($found, 'Challenge with authorization association should be found');
    }

    public function testCountWithAuthorizationAssociation(): void
    {
        $repository = self::getService(ChallengeRepository::class);
        $challenge = $this->createBasicChallengeForTest();
        $authorization = $challenge->getAuthorization();
        $initialCount = $repository->count(['authorization' => $authorization]);

        $challenge->setChallengeUrl('https://count-assoc-test.example.com/challenge');
        $repository->save($challenge);

        $newCount = $repository->count(['authorization' => $authorization]);
        $this->assertSame($initialCount + 1, $newCount);
    }

    protected function createNewEntity(): object
    {
        $entityManager = self::getEntityManager();

        // Create account
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"test": "jwk"}');
        $entityManager->persist($account);

        // Create order
        $order = new Order();
        $order->setAccount($account);
        $order->setStatus(OrderStatus::PENDING);
        $order->setOrderUrl('https://test.example.com/order-' . uniqid());
        $entityManager->persist($order);

        // Create identifier
        $identifier = new Identifier();
        $identifier->setType('dns');
        $identifier->setValue('test-' . uniqid() . '.example.com');
        $identifier->setOrder($order);
        $entityManager->persist($identifier);

        // Create authorization
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setAuthorizationUrl('https://test.example.com/auth-' . uniqid());
        $authorization->setIdentifier($identifier);
        $authorization->setOrder($order);
        $entityManager->persist($authorization);

        // Create challenge
        $entity = new Challenge();
        $entity->setStatus(ChallengeStatus::PENDING);
        $entity->setType(ChallengeType::DNS_01);
        $entity->setChallengeUrl('https://test.example.com/challenge-' . uniqid());
        $entity->setToken('test-token-' . uniqid());
        $entity->setKeyAuthorization('test-key-auth-' . uniqid());
        $entity->setAuthorization($authorization);

        return $entity;
    }

    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(ChallengeRepository::class);
    }
}
