<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public const ORDER_PENDING_REFERENCE = 'order-pending';
    public const ORDER_READY_REFERENCE = 'order-ready';
    public const ORDER_VALID_REFERENCE = 'order-valid';

    public function getDependencies(): array
    {
        return [
            AccountFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // 从AccountFixtures获取账户引用
        // 注意: AccountFixtures 需要先添加引用支持
        $account1 = $this->getReference(AccountFixtures::ACCOUNT_STAGING_REFERENCE, Account::class);
        $account2 = $this->getReference(AccountFixtures::ACCOUNT_PROD_REFERENCE, Account::class);

        // 创建待处理订单
        $order1 = new Order();
        $order1->setAccount($account1);
        $order1->setOrderUrl('https://acme-staging-v02.api.letsencrypt.org/acme/order/test123');
        $order1->setStatus(OrderStatus::PENDING);
        $order1->setExpiresTime(new \DateTimeImmutable('+7 days'));
        $order1->setFinalizeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/order/test123/finalize');
        $order1->setValid(false);

        // 创建准备就绪订单
        $order2 = new Order();
        $order2->setAccount($account1);
        $order2->setOrderUrl('https://acme-staging-v02.api.letsencrypt.org/acme/order/test456');
        $order2->setStatus(OrderStatus::READY);
        $order2->setExpiresTime(new \DateTimeImmutable('+5 days'));
        $order2->setFinalizeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/order/test456/finalize');
        $order2->setValid(false);

        // 创建有效订单（已完成）
        $order3 = new Order();
        $order3->setAccount($account2);
        $order3->setOrderUrl('https://acme-v02.api.letsencrypt.org/acme/order/prod789');
        $order3->setStatus(OrderStatus::VALID);
        $order3->setExpiresTime(new \DateTimeImmutable('+3 days'));
        $order3->setFinalizeUrl('https://acme-v02.api.letsencrypt.org/acme/order/prod789/finalize');
        $order3->setCertificateUrl('https://acme-v02.api.letsencrypt.org/acme/cert/abc123def456');
        $order3->setValid(true);

        $manager->persist($order1);
        $manager->persist($order2);
        $manager->persist($order3);
        $manager->flush();

        // 保存引用供其他 Fixtures 使用
        $this->addReference(self::ORDER_PENDING_REFERENCE, $order1);
        $this->addReference(self::ORDER_READY_REFERENCE, $order2);
        $this->addReference(self::ORDER_VALID_REFERENCE, $order3);
    }
}
