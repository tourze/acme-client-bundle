<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;

#[When(env: 'test')]
#[When(env: 'dev')]
class AccountFixtures extends Fixture
{
    public const ACCOUNT_REFERENCE = 'account';

    public function load(ObjectManager $manager): void
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setAccountUrl('https://acme-staging-v02.api.letsencrypt.org/acme/acct/123456');
        $account->setStatus(AccountStatus::VALID);
        $account->setContacts(['mailto:test@example.com']);
        $account->setTermsOfServiceAgreed(true);

        $manager->persist($account);
        $this->addReference(self::ACCOUNT_REFERENCE, $account);

        $manager->flush();
    }

    private function generateTestPrivateKey(): string
    {
        // 使用固定的测试私钥，避免每次运行时生成新的密钥
        return <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA0Z3VS5JJcds3xfn/ygWyF8PbnGy0AHB7MmE8qTCXBgVMY0vR
test_key_for_fixtures_only_do_not_use_in_production
-----END RSA PRIVATE KEY-----
PEM;
    }
}
