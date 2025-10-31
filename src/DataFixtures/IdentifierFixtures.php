<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;

class IdentifierFixtures extends Fixture implements DependentFixtureInterface
{
    public const IDENTIFIER_ACME_TEST_REFERENCE = 'identifier-acme-test';
    public const IDENTIFIER_WWW_ACME_TEST_REFERENCE = 'identifier-www-acme-test';
    public const IDENTIFIER_TEST_ACME_REFERENCE = 'identifier-test-acme';
    public const IDENTIFIER_WILDCARD_PROD_REFERENCE = 'identifier-wildcard-prod';
    public const IDENTIFIER_PROD_ACME_REFERENCE = 'identifier-prod-acme';

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $orderPending = $this->getReference(OrderFixtures::ORDER_PENDING_REFERENCE, Order::class);
        $orderReady = $this->getReference(OrderFixtures::ORDER_READY_REFERENCE, Order::class);
        $orderValid = $this->getReference(OrderFixtures::ORDER_VALID_REFERENCE, Order::class);

        // 为待处理订单创建标识符
        $identifier1 = new Identifier();
        $identifier1->setOrder($orderPending);
        $identifier1->setType('dns');
        $identifier1->setValue('acme-test.tourze.dev');
        $identifier1->setWildcard(false);
        $identifier1->setValid(false);

        $identifier2 = new Identifier();
        $identifier2->setOrder($orderPending);
        $identifier2->setType('dns');
        $identifier2->setValue('www.acme-test.tourze.dev');
        $identifier2->setWildcard(false);
        $identifier2->setValid(false);

        // 为准备就绪订单创建标识符
        $identifier3 = new Identifier();
        $identifier3->setOrder($orderReady);
        $identifier3->setType('dns');
        $identifier3->setValue('test.acme.tourze.dev');
        $identifier3->setWildcard(false);
        $identifier3->setValid(true);

        // 为有效订单创建通配符标识符
        $identifier4 = new Identifier();
        $identifier4->setOrder($orderValid);
        $identifier4->setType('dns');
        $identifier4->setValue('*.prod.acme.tourze.dev');
        $identifier4->setWildcard(true);
        $identifier4->setValid(true);

        $identifier5 = new Identifier();
        $identifier5->setOrder($orderValid);
        $identifier5->setType('dns');
        $identifier5->setValue('prod.acme.tourze.dev');
        $identifier5->setWildcard(false);
        $identifier5->setValid(true);

        $manager->persist($identifier1);
        $manager->persist($identifier2);
        $manager->persist($identifier3);
        $manager->persist($identifier4);
        $manager->persist($identifier5);
        $manager->flush();

        // 保存引用供其他 Fixtures 使用
        $this->addReference(self::IDENTIFIER_ACME_TEST_REFERENCE, $identifier1);
        $this->addReference(self::IDENTIFIER_WWW_ACME_TEST_REFERENCE, $identifier2);
        $this->addReference(self::IDENTIFIER_TEST_ACME_REFERENCE, $identifier3);
        $this->addReference(self::IDENTIFIER_WILDCARD_PROD_REFERENCE, $identifier4);
        $this->addReference(self::IDENTIFIER_PROD_ACME_REFERENCE, $identifier5);
    }
}
