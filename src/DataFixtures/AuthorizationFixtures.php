<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;

class AuthorizationFixtures extends Fixture implements DependentFixtureInterface
{
    public const AUTHORIZATION_ACME_TEST_PENDING_REFERENCE = 'authorization-acme-test-pending';
    public const AUTHORIZATION_WWW_PENDING_REFERENCE = 'authorization-www-pending';
    public const AUTHORIZATION_TEST_VALID_REFERENCE = 'authorization-test-valid';

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
            IdentifierFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $orderPending = $this->getReference(OrderFixtures::ORDER_PENDING_REFERENCE, Order::class);
        $orderReady = $this->getReference(OrderFixtures::ORDER_READY_REFERENCE, Order::class);
        $orderValid = $this->getReference(OrderFixtures::ORDER_VALID_REFERENCE, Order::class);

        $identifierExample = $this->getReference(IdentifierFixtures::IDENTIFIER_ACME_TEST_REFERENCE, Identifier::class);
        $identifierWww = $this->getReference(IdentifierFixtures::IDENTIFIER_WWW_ACME_TEST_REFERENCE, Identifier::class);
        $identifierTest = $this->getReference(IdentifierFixtures::IDENTIFIER_TEST_ACME_REFERENCE, Identifier::class);

        // 为待处理订单创建授权
        $authorization1 = new Authorization();
        $authorization1->setOrder($orderPending);
        $authorization1->setIdentifier($identifierExample);
        $authorization1->setAuthorizationUrl('https://acme-staging-v02.api.letsencrypt.org/acme/authz/test123');
        $authorization1->setStatus(AuthorizationStatus::PENDING);
        $authorization1->setExpiresTime(new \DateTimeImmutable('+30 days'));
        $authorization1->setWildcard(false);
        $authorization1->setValid(false);

        $authorization2 = new Authorization();
        $authorization2->setOrder($orderPending);
        $authorization2->setIdentifier($identifierWww);
        $authorization2->setAuthorizationUrl('https://acme-staging-v02.api.letsencrypt.org/acme/authz/test456');
        $authorization2->setStatus(AuthorizationStatus::PENDING);
        $authorization2->setExpiresTime(new \DateTimeImmutable('+30 days'));
        $authorization2->setWildcard(false);
        $authorization2->setValid(false);

        // 为准备就绪订单创建有效授权
        $authorization3 = new Authorization();
        $authorization3->setOrder($orderReady);
        $authorization3->setIdentifier($identifierTest);
        $authorization3->setAuthorizationUrl('https://acme-staging-v02.api.letsencrypt.org/acme/authz/test789');
        $authorization3->setStatus(AuthorizationStatus::VALID);
        $authorization3->setExpiresTime(new \DateTimeImmutable('+25 days'));
        $authorization3->setWildcard(false);
        $authorization3->setValid(true);

        $manager->persist($authorization1);
        $manager->persist($authorization2);
        $manager->persist($authorization3);
        $manager->flush();

        // 保存引用供其他 Fixtures 使用
        $this->addReference(self::AUTHORIZATION_ACME_TEST_PENDING_REFERENCE, $authorization1);
        $this->addReference(self::AUTHORIZATION_WWW_PENDING_REFERENCE, $authorization2);
        $this->addReference(self::AUTHORIZATION_TEST_VALID_REFERENCE, $authorization3);
    }
}
