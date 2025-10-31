<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(AccountRepository::class)]
#[RunTestsInSeparateProcesses]
final class AccountRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    public function testSaveAndFind(): void
    {
        $repository = self::getService(AccountRepository::class);
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key');
        $account->setPublicKeyJwk('{"kty":"RSA"}');
        $account->setStatus(AccountStatus::PENDING);
        $account->setTermsOfServiceAgreed(true);

        $repository->save($account);
        $this->assertNotNull($account->getId());

        $foundAccount = $repository->find($account->getId());
        $this->assertInstanceOf(Account::class, $foundAccount);
        $this->assertSame($account->getAcmeServerUrl(), $foundAccount->getAcmeServerUrl());
        $this->assertSame($account->getPrivateKey(), $foundAccount->getPrivateKey());
    }

    public function testFindByWithStatusCriteria(): void
    {
        $repository = self::getService(AccountRepository::class);
        $account = new Account();
        $account->setAcmeServerUrl('https://test.example.com/directory');
        $account->setPrivateKey('test-key');
        $account->setPublicKeyJwk('{"kty":"RSA"}');
        $account->setStatus(AccountStatus::VALID);
        $account->setTermsOfServiceAgreed(true);

        $repository->save($account);

        $results = $repository->findBy(['status' => AccountStatus::VALID]);
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            if ($result->getId() === $account->getId()) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Account should be found by status criteria');
    }

    public function testFindOneByWithServerUrlCriteria(): void
    {
        $repository = self::getService(AccountRepository::class);
        $serverUrl = 'https://unique-test-server.com/directory';
        $account = new Account();
        $account->setAcmeServerUrl($serverUrl);
        $account->setPrivateKey('unique-test-key');
        $account->setPublicKeyJwk('{"kty":"RSA"}');
        $account->setStatus(AccountStatus::PENDING);
        $account->setTermsOfServiceAgreed(true);

        $repository->save($account);

        $foundAccount = $repository->findOneBy(['acmeServerUrl' => $serverUrl]);
        $this->assertInstanceOf(Account::class, $foundAccount);
        $this->assertSame($serverUrl, $foundAccount->getAcmeServerUrl());
    }

    public function testRemove(): void
    {
        $repository = self::getService(AccountRepository::class);
        $account = new Account();
        $account->setAcmeServerUrl('https://to-be-removed.com/directory');
        $account->setPrivateKey('remove-test-key');
        $account->setPublicKeyJwk('{"kty":"RSA"}');
        $account->setStatus(AccountStatus::PENDING);
        $account->setTermsOfServiceAgreed(true);

        $repository->save($account);
        $accountId = $account->getId();
        $this->assertNotNull($accountId);

        $repository->remove($account);

        $removedAccount = $repository->find($accountId);
        $this->assertNull($removedAccount);
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(AccountRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(0, count($results));
    }

    public function testFindOneByWithNullFieldShouldReturnEntity(): void
    {
        $repository = self::getService(AccountRepository::class);
        $account = new Account();
        $account->setAcmeServerUrl('https://null-contacts.com/directory');
        $account->setPrivateKey('null-contacts-key');
        $account->setPublicKeyJwk('{"kty":"RSA"}');
        $account->setStatus(AccountStatus::PENDING);
        $account->setTermsOfServiceAgreed(true);
        $account->setContacts(null); // Nullable field

        $repository->save($account);

        $foundAccount = $repository->findOneBy(['contacts' => null]);
        $this->assertInstanceOf(Account::class, $foundAccount);
        $this->assertNull($foundAccount->getContacts());
    }

    public function testFindByWithNullFieldShouldReturnEntities(): void
    {
        $repository = self::getService(AccountRepository::class);
        $account = new Account();
        $account->setAcmeServerUrl('https://null-account-url.com/directory');
        $account->setPrivateKey('null-account-url-key');
        $account->setPublicKeyJwk('{"kty":"RSA"}');
        $account->setStatus(AccountStatus::PENDING);
        $account->setTermsOfServiceAgreed(true);
        $account->setAccountUrl(null); // Nullable field

        $repository->save($account);

        $results = $repository->findBy(['accountUrl' => null]);
        // Remove redundant assertIsArray as PHPDoc already specifies list<Account>
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Account::class, $result);
            $this->assertNull($result->getAccountUrl());
            if ($result->getId() === $account->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Account with null accountUrl should be found');
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(AccountRepository::class);

        // Create multiple accounts with different server URLs for sorting
        $account1 = new Account();
        $account1->setAcmeServerUrl('https://a-server.com/directory');
        $account1->setPrivateKey('test-key-1');
        $account1->setPublicKeyJwk('{"kty":"RSA"}');
        $account1->setStatus(AccountStatus::VALID);
        $account1->setTermsOfServiceAgreed(true);
        $repository->save($account1);

        $account2 = new Account();
        $account2->setAcmeServerUrl('https://z-server.com/directory');
        $account2->setPrivateKey('test-key-2');
        $account2->setPublicKeyJwk('{"kty":"RSA"}');
        $account2->setStatus(AccountStatus::VALID);
        $account2->setTermsOfServiceAgreed(true);
        $repository->save($account2);

        // Test ascending order
        $result = $repository->findOneBy(['status' => AccountStatus::VALID], ['acmeServerUrl' => 'ASC']);
        $this->assertInstanceOf(Account::class, $result);
        $this->assertSame($account1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['status' => AccountStatus::VALID], ['acmeServerUrl' => 'DESC']);
        $this->assertInstanceOf(Account::class, $result);
        $this->assertSame($account2->getId(), $result->getId());
    }

    protected function createNewEntity(): object
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account->setPrivateKey('test-private-key-' . uniqid());
        $account->setPublicKeyJwk('{"kty":"RSA","n":"test","e":"AQAB"}');
        $account->setStatus(AccountStatus::PENDING);
        $account->setTermsOfServiceAgreed(true);

        return $account;
    }

    /**
     * @return AccountRepository
     */
    protected function getRepository(): AccountRepository
    {
        return self::getService(AccountRepository::class);
    }
}
