<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\OrderStatus;

#[When(env: 'test')]
#[When(env: 'dev')]
class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public const ORDER_REFERENCE = 'order';

    public function load(ObjectManager $manager): void
    {
        $order = new Order();
        $order->setAccount($this->getReference(AccountFixtures::ACCOUNT_REFERENCE, Account::class));
        $order->setOrderUrl('https://acme-staging-v02.api.letsencrypt.org/acme/order/123456/789');
        $order->setStatus(OrderStatus::PENDING);
        $order->setExpiresTime(new \DateTimeImmutable('+7 days'));
        $order->setFinalizeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/finalize/123456/789');

        $manager->persist($order);
        $this->addReference(self::ORDER_REFERENCE, $order);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AccountFixtures::class,
        ];
    }
}
