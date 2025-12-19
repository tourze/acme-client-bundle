<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;

#[When(env: 'test')]
#[When(env: 'dev')]
class IdentifierFixtures extends Fixture implements DependentFixtureInterface
{
    public const IDENTIFIER_REFERENCE = 'identifier';

    public function load(ObjectManager $manager): void
    {
        $identifier = new Identifier();
        $identifier->setOrder($this->getReference(OrderFixtures::ORDER_REFERENCE, Order::class));
        $identifier->setType('dns');
        $identifier->setValue('test-domain.local'); // @phpstan-ignore shipmonk.dataFixturesPlaceholderImageService
        $identifier->setWildcard(false);

        $manager->persist($identifier);
        $this->addReference(self::IDENTIFIER_REFERENCE, $identifier);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
        ];
    }
}
