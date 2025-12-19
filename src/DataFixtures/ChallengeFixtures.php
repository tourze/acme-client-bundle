<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;

#[When(env: 'test')]
#[When(env: 'dev')]
class ChallengeFixtures extends Fixture implements DependentFixtureInterface
{
    public const CHALLENGE_REFERENCE = 'challenge';

    public function load(ObjectManager $manager): void
    {
        $challenge = new Challenge();
        $challenge->setAuthorization($this->getReference(AuthorizationFixtures::AUTHORIZATION_REFERENCE, Authorization::class));
        $challenge->setChallengeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/chall/123456/abc');
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::PENDING);
        $challenge->setToken('test-token-for-fixtures');
        $challenge->setKeyAuthorization('test-token-for-fixtures.thumbprint');
        $challenge->setDnsRecordName('_acme-challenge.test-domain.local'); // @phpstan-ignore shipmonk.dataFixturesPlaceholderImageService
        $challenge->setDnsRecordValue('test-dns-record-value');

        $manager->persist($challenge);
        $this->addReference(self::CHALLENGE_REFERENCE, $challenge);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AuthorizationFixtures::class,
        ];
    }
}
