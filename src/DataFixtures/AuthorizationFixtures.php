<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;

#[When(env: 'test')]
#[When(env: 'dev')]
class AuthorizationFixtures extends Fixture implements DependentFixtureInterface
{
    public const AUTHORIZATION_REFERENCE = 'authorization';

    public function load(ObjectManager $manager): void
    {
        $authorization = new Authorization();
        $authorization->setOrder($this->getReference(OrderFixtures::ORDER_REFERENCE, Order::class));
        $authorization->setIdentifier($this->getReference(IdentifierFixtures::IDENTIFIER_REFERENCE, Identifier::class));
        $authorization->setAuthorizationUrl('https://acme-staging-v02.api.letsencrypt.org/acme/authz/123456');
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setExpiresTime(new \DateTimeImmutable('+7 days'));
        $authorization->setWildcard(false);

        $manager->persist($authorization);
        $this->addReference(self::AUTHORIZATION_REFERENCE, $authorization);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OrderFixtures::class,
            IdentifierFixtures::class,
        ];
    }
}
