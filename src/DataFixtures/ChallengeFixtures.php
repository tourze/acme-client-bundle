<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;

class ChallengeFixtures extends Fixture implements DependentFixtureInterface
{
    public const CHALLENGE_ACME_TEST_PENDING_REFERENCE = 'challenge-acme-test-pending';
    public const CHALLENGE_WWW_PENDING_REFERENCE = 'challenge-www-pending';
    public const CHALLENGE_TEST_VALID_REFERENCE = 'challenge-test-valid';

    public function getDependencies(): array
    {
        return [
            AuthorizationFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $authExamplePending = $this->getReference(AuthorizationFixtures::AUTHORIZATION_ACME_TEST_PENDING_REFERENCE, Authorization::class);
        $authWwwPending = $this->getReference(AuthorizationFixtures::AUTHORIZATION_WWW_PENDING_REFERENCE, Authorization::class);
        $authTestValid = $this->getReference(AuthorizationFixtures::AUTHORIZATION_TEST_VALID_REFERENCE, Authorization::class);

        // 为 acme-test.tourze.dev 创建待处理的 DNS 质询
        $challenge1 = new Challenge();
        $challenge1->setAuthorization($authExamplePending);
        $challenge1->setChallengeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/challenge/test123');
        $challenge1->setType(ChallengeType::DNS_01);
        $challenge1->setStatus(ChallengeStatus::PENDING);
        $challenge1->setToken('AbCdEf123456789_test_token_acme');
        $challenge1->setKeyAuthorization('AbCdEf123456789_test_token_acme.tGzv3JOkF0XG5Qx2ArHD37foB_cpUfbdOsJsxbIZwIw');
        $challenge1->setDnsRecordName('_acme-challenge.acme-test.tourze.dev');
        $challenge1->setDnsRecordValue('tGzv3JOkF0XG5Qx2ArHD37foB_cpUfbdOsJsxbIZwIw');
        $challenge1->setValid(false);

        // 为 www.acme-test.tourze.dev 创建待处理的 DNS 质询
        $challenge2 = new Challenge();
        $challenge2->setAuthorization($authWwwPending);
        $challenge2->setChallengeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/challenge/test456');
        $challenge2->setType(ChallengeType::DNS_01);
        $challenge2->setStatus(ChallengeStatus::PENDING);
        $challenge2->setToken('XyZ789_another_test_token_www');
        $challenge2->setKeyAuthorization('XyZ789_another_test_token_www.mNbVcXz8901YuIo2PlKj6EtGqWxRfAdS7jsHmKbEzQr');
        $challenge2->setDnsRecordName('_acme-challenge.www.acme-test.tourze.dev');
        $challenge2->setDnsRecordValue('mNbVcXz8901YuIo2PlKj6EtGqWxRfAdS7jsHmKbEzQr');
        $challenge2->setValid(false);

        // 为 test.acme.tourze.dev 创建已验证的 DNS 质询
        $challenge3 = new Challenge();
        $challenge3->setAuthorization($authTestValid);
        $challenge3->setChallengeUrl('https://acme-staging-v02.api.letsencrypt.org/acme/challenge/test789');
        $challenge3->setType(ChallengeType::DNS_01);
        $challenge3->setStatus(ChallengeStatus::VALID);
        $challenge3->setToken('ValidToken_test_acme_domain');
        $challenge3->setKeyAuthorization('ValidToken_test_acme_domain.pQrStU3VwXyZ0AbCdEf4GhIjKlMnOpQrStUvWxYz123');
        $challenge3->setDnsRecordName('_acme-challenge.test.acme.tourze.dev');
        $challenge3->setDnsRecordValue('pQrStU3VwXyZ0AbCdEf4GhIjKlMnOpQrStUvWxYz123');
        $challenge3->setValidatedTime(new \DateTimeImmutable('-1 hour'));
        $challenge3->setValid(true);

        $manager->persist($challenge1);
        $manager->persist($challenge2);
        $manager->persist($challenge3);
        $manager->flush();

        // 保存引用供其他 Fixtures 使用
        $this->addReference(self::CHALLENGE_ACME_TEST_PENDING_REFERENCE, $challenge1);
        $this->addReference(self::CHALLENGE_WWW_PENDING_REFERENCE, $challenge2);
        $this->addReference(self::CHALLENGE_TEST_VALID_REFERENCE, $challenge3);
    }
}
