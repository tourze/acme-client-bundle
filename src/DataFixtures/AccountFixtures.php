<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;

class AccountFixtures extends Fixture
{
    public const ACCOUNT_STAGING_REFERENCE = 'account-staging';
    public const ACCOUNT_PROD_REFERENCE = 'account-prod';

    public function load(ObjectManager $manager): void
    {
        // 创建测试用 ACME 账户
        $account1 = new Account();
        $account1->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account1->setPrivateKey('-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC...\n-----END PRIVATE KEY-----');
        $account1->setPublicKeyJwk('{"kty":"RSA","n":"test-public-key-1","e":"AQAB"}');
        $account1->setStatus(AccountStatus::VALID);
        $account1->setContacts(['mailto:test@acme-test.tourze.dev']);
        $account1->setTermsOfServiceAgreed(true);

        $account2 = new Account();
        $account2->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account2->setPrivateKey('-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQD...\n-----END PRIVATE KEY-----');
        $account2->setPublicKeyJwk('{"kty":"RSA","n":"test-public-key-2","e":"AQAB"}');
        $account2->setStatus(AccountStatus::VALID);
        $account2->setContacts(['mailto:prod@acme-prod.tourze.dev']);
        $account2->setTermsOfServiceAgreed(true);

        $manager->persist($account1);
        $manager->persist($account2);
        $manager->flush();

        // 保存引用供其他 Fixtures 使用
        $this->addReference(self::ACCOUNT_STAGING_REFERENCE, $account1);
        $this->addReference(self::ACCOUNT_PROD_REFERENCE, $account2);
    }
}
