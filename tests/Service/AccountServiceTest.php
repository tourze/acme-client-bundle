<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AccountService::class)]
#[RunTestsInSeparateProcesses]
final class AccountServiceTest extends AbstractIntegrationTestCase
{
    private AccountService $service;

    /** @var AcmeApiClient&MockObject */
    private AcmeApiClient $apiClient;

    protected function onSetUp(): void
    {
        /*
         * 使用具体类 AcmeApiClient 的 Mock 对象
         * 原因：AcmeApiClient 是核心 API 客户端服务类，没有对应的接口抽象
         * 合理性：在单元测试中需要隔离外部 ACME API 调用，使用 Mock 是必要的测试实践
         * 替代方案：可以考虑为 AcmeApiClient 创建接口抽象，但会增加代码复杂度
         */
        $this->apiClient = $this->createMock(AcmeApiClient::class);
        self::getContainer()->set(AcmeApiClient::class, $this->apiClient);

        $this->service = self::getService(AccountService::class);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(AccountService::class, $this->service);
    }

    public function testRegisterAccount(): void
    {
        $contacts = ['mailto:admin@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();

        $mockResponse = [
            'status' => 'valid',
            'contact' => $contacts,
        ];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'newAccount',
                ['contact' => $contacts, 'termsOfServiceAgreed' => true],
                self::anything()
            )
            ->willReturn($mockResponse)
        ;

        $result = $this->service->registerAccount($contacts, true, $privateKeyPem);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertEquals(AccountStatus::VALID, $result->getStatus());
        $this->assertEquals($contacts, $result->getContacts());
        $this->assertEquals($privateKeyPem, $result->getPrivateKey());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testRegisterAccountWithEmptyContacts(): void
    {
        $privateKeyPem = $this->generateTestPrivateKey();

        $mockResponse = [
            'status' => 'valid',
            'contact' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'newAccount',
                ['contact' => [], 'termsOfServiceAgreed' => true],
                self::anything()
            )
            ->willReturn($mockResponse)
        ;

        $result = $this->service->registerAccount([], true, $privateKeyPem);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertEquals([], $result->getContacts());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testFindAccountByContactEmail(): void
    {
        // 先创建一个测试账户
        $account = new Account();
        $account->setContacts(['mailto:test@example.com']);
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setStatus(AccountStatus::VALID);
        $account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account->setPublicKeyJwk('{"kty":"RSA","n":"test","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account);

        $result = $this->service->findAccountByEmail('test@example.com');

        $this->assertNotNull($result);
        $contacts = $result->getContacts();
        $this->assertNotNull($contacts);
        $this->assertContains('mailto:test@example.com', $contacts);
    }

    public function testFindAccountByContactEmailNotFound(): void
    {
        $result = $this->service->findAccountByEmail('notfound@example.com');

        $this->assertNull($result);
    }

    public function testFindAccountByEmailWithServerUrl(): void
    {
        // 创建两个不同服务器的账户
        $account1 = new Account();
        $account1->setContacts(['mailto:test@example.com']);
        $account1->setPrivateKey($this->generateTestPrivateKey());
        $account1->setStatus(AccountStatus::VALID);
        $account1->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account1->setPublicKeyJwk('{"kty":"RSA","n":"test1","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account1);

        $account2 = new Account();
        $account2->setContacts(['mailto:test@example.com']);
        $account2->setPrivateKey($this->generateTestPrivateKey());
        $account2->setStatus(AccountStatus::VALID);
        $account2->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account2->setPublicKeyJwk('{"kty":"RSA","n":"test2","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account2);

        // 查找特定服务器的账户
        $result = $this->service->findAccountByEmail('test@example.com', 'https://acme-staging-v02.api.letsencrypt.org/directory');

        $this->assertNotNull($result);
        $this->assertEquals('https://acme-staging-v02.api.letsencrypt.org/directory', $result->getAcmeServerUrl());
    }

    public function testFindAccountByEmailWithMultipleContacts(): void
    {
        // 创建一个有多个联系方式的账户
        $account = new Account();
        $account->setContacts(['mailto:admin@example.com', 'mailto:test@example.com', 'mailto:support@example.com']);
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setStatus(AccountStatus::VALID);
        $account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account->setPublicKeyJwk('{"kty":"RSA","n":"multi","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account);

        // 使用中间的邮箱查找
        $result = $this->service->findAccountByEmail('test@example.com');

        $this->assertNotNull($result);
        $contacts = $result->getContacts();
        $this->assertNotNull($contacts);
        $this->assertContains('mailto:test@example.com', $contacts);
    }

    public function testFindAccountsByServerUrl(): void
    {
        $serverUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';

        // 创建多个账户
        $account1 = new Account();
        $account1->setContacts(['mailto:test1@example.com']);
        $account1->setPrivateKey($this->generateTestPrivateKey());
        $account1->setStatus(AccountStatus::VALID);
        $account1->setAcmeServerUrl($serverUrl);
        $account1->setPublicKeyJwk('{"kty":"RSA","n":"server1","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account1);

        $account2 = new Account();
        $account2->setContacts(['mailto:test2@example.com']);
        $account2->setPrivateKey($this->generateTestPrivateKey());
        $account2->setStatus(AccountStatus::VALID);
        $account2->setAcmeServerUrl($serverUrl);
        $account2->setPublicKeyJwk('{"kty":"RSA","n":"server2","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account2);

        // 创建一个不同服务器的账户
        $account3 = new Account();
        $account3->setContacts(['mailto:test3@example.com']);
        $account3->setPrivateKey($this->generateTestPrivateKey());
        $account3->setStatus(AccountStatus::VALID);
        $account3->setAcmeServerUrl('https://acme-v02.api.letsencrypt.org/directory');
        $account3->setPublicKeyJwk('{"kty":"RSA","n":"server3","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account3);

        $result = $this->service->findAccountsByServerUrl($serverUrl);

        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $account) {
            $this->assertEquals($serverUrl, $account->getAcmeServerUrl());
        }
    }

    public function testFindAccountsByServerUrlNotFound(): void
    {
        $result = $this->service->findAccountsByServerUrl('https://non-existent.example.com/directory');

        $this->assertEmpty($result);
    }

    public function testRegisterAccountByEmail(): void
    {
        $email = 'newaccount@example.com';
        $serverUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';

        $mockResponse = [
            'status' => 'valid',
            'contact' => ["mailto:{$email}"],
            'location' => 'https://acme.example.com/account/456',
        ];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'newAccount',
                ['contact' => ["mailto:{$email}"], 'termsOfServiceAgreed' => true],
                self::anything()
            )
            ->willReturn($mockResponse)
        ;

        $result = $this->service->registerAccountByEmail($email, $serverUrl);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertEquals(AccountStatus::VALID, $result->getStatus());
        $this->assertEquals(["mailto:{$email}"], $result->getContacts());
        $this->assertEquals($serverUrl, $result->getAcmeServerUrl());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($result);
    }

    public function testRegisterAccountByEmailWithCustomKeySize(): void
    {
        $email = 'custom@example.com';
        $serverUrl = 'https://acme-v02.api.letsencrypt.org/directory';
        $keySize = 4096;

        $mockResponse = [
            'status' => 'valid',
            'contact' => ["mailto:{$email}"],
        ];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn($mockResponse)
        ;

        $result = $this->service->registerAccountByEmail($email, $serverUrl, $keySize);

        $this->assertInstanceOf(Account::class, $result);

        // 验证生成的密钥大小
        $privateKey = openssl_pkey_get_private($result->getPrivateKey());
        $this->assertNotFalse($privateKey, 'Private key should be valid');

        $keyDetails = openssl_pkey_get_details($privateKey);
        $this->assertNotFalse($keyDetails, 'Key details should be available');
        $this->assertEquals($keySize, $keyDetails['bits']);
    }

    public function testRegisterAccountByEmailWithDisabledTermsOfService(): void
    {
        $email = 'noteagree@example.com';
        $serverUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'newAccount',
                ['contact' => ["mailto:{$email}"], 'termsOfServiceAgreed' => false],
                self::anything()
            )
            ->willReturn(['status' => 'valid', 'contact' => ["mailto:{$email}"]])
        ;

        $result = $this->service->registerAccountByEmail($email, $serverUrl, 2048, false);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertFalse($result->isTermsOfServiceAgreed());
    }

    public function testRegisterAccountByEmailWithApiError(): void
    {
        $email = 'error@example.com';
        $serverUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willThrowException(new AcmeServerException('Registration failed'))
        ;

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Account registration failed: Registration failed');

        $this->service->registerAccountByEmail($email, $serverUrl);
    }

    public function testFindAccountsByStatus(): void
    {
        // 先创建一些测试账户
        $account1 = new Account();
        $account1->setContacts(['mailto:test1@example.com']);
        $account1->setPrivateKey($this->generateTestPrivateKey());
        $account1->setStatus(AccountStatus::VALID);
        $account1->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account1->setPublicKeyJwk('{"kty":"RSA","n":"test1","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account1);

        $account2 = new Account();
        $account2->setContacts(['mailto:test2@example.com']);
        $account2->setPrivateKey($this->generateTestPrivateKey());
        $account2->setStatus(AccountStatus::VALID);
        $account2->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account2->setPublicKeyJwk('{"kty":"RSA","n":"test2","e":"AQAB"}');
        $repository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $repository);
        $repository->save($account2);

        $result = $this->service->findAccountsByStatus(AccountStatus::VALID);

        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $account) {
            $this->assertEquals(AccountStatus::VALID, $account->getStatus());
        }
    }

    public function testIsAccountValid(): void
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setStatus(AccountStatus::VALID);

        $isValid = $this->service->isAccountValid($account);

        $this->assertTrue($isValid);
    }

    public function testIsAccountValidWithInvalidStatus(): void
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setStatus(AccountStatus::DEACTIVATED);

        $isValid = $this->service->isAccountValid($account);

        $this->assertFalse($isValid);
    }

    public function testDeactivateAccount(): void
    {
        $account = new Account();
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setAccountUrl('https://acme.example.com/account/123');
        $account->setStatus(AccountStatus::VALID);
        $account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account->setPublicKeyJwk('{"kty":"RSA","n":"testdeactivate","e":"AQAB"}');

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                $account->getAccountUrl(),
                ['status' => 'deactivated'],
                self::anything(),
                $account->getAccountUrl()
            )
            ->willReturn(['status' => 'deactivated'])
        ;

        $result = $this->service->deactivateAccount($account);

        $this->assertSame($account, $result);
        $this->assertEquals(AccountStatus::DEACTIVATED, $account->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($account);
    }

    public function testUpdateAccountContacts(): void
    {
        $account = new Account();
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setAccountUrl('https://acme.example.com/account/123');
        $account->setContacts(['mailto:old@example.com']);
        $account->setAcmeServerUrl('https://acme-staging-v02.api.letsencrypt.org/directory');
        $account->setPublicKeyJwk('{"kty":"RSA","n":"testupdate","e":"AQAB"}');

        $newContacts = ['mailto:new@example.com'];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                $account->getAccountUrl(),
                ['contact' => $newContacts],
                self::anything(),
                $account->getAccountUrl()
            )
            ->willReturn(['contact' => $newContacts])
        ;

        $result = $this->service->updateAccountContacts($account, $newContacts);

        $this->assertSame($account, $result);
        $this->assertEquals($newContacts, $account->getContacts());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($account);
    }

    public function testBusinessScenarioAccountLifecycle(): void
    {
        $contacts = ['mailto:business@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();

        // 设置连续的三个 post 调用的期望
        $callCount = 0;
        $this->apiClient->expects($this->exactly(3))
            ->method('post')
            ->willReturnCallback(function ($url, $payload, $privateKey, $accountUrl = null) use ($contacts, &$callCount) {
                ++$callCount;

                switch ($callCount) {
                    case 1: // 注册账户
                        $this->assertEquals('newAccount', $url);
                        $this->assertEquals(['contact' => $contacts, 'termsOfServiceAgreed' => true], $payload);

                        return ['status' => 'valid', 'contact' => $contacts, 'location' => 'https://acme.example.com/account/123'];

                    case 2: // 更新联系方式
                        $this->assertEquals('https://acme.example.com/account/123', $url);
                        $this->assertEquals(['contact' => ['mailto:updated@example.com']], $payload);

                        return ['contact' => ['mailto:updated@example.com']];

                    case 3: // 停用账户
                        $this->assertEquals('https://acme.example.com/account/123', $url);
                        $this->assertEquals(['status' => 'deactivated'], $payload);

                        return ['status' => 'deactivated'];

                    default:
                        self::fail('Unexpected post call');
                }
            })
        ;

        // 1. 注册账户
        $account = $this->service->registerAccount($contacts, true, $privateKeyPem);
        $this->assertEquals(AccountStatus::VALID, $account->getStatus());

        // 2. 更新联系方式
        $newContacts = ['mailto:updated@example.com'];
        $updatedAccount = $this->service->updateAccountContacts($account, $newContacts);
        $this->assertEquals($newContacts, $updatedAccount->getContacts());

        // 3. 停用账户

        $deactivatedAccount = $this->service->deactivateAccount($account);
        $this->assertEquals(AccountStatus::DEACTIVATED, $deactivatedAccount->getStatus());

        // 验证所有实体状态都已持久化
        $this->assertEntityPersisted($deactivatedAccount);
    }

    public function testEdgeCasesEmptyPrivateKey(): void
    {
        $this->expectException(AbstractAcmeException::class);

        $this->service->registerAccount(['mailto:test@example.com'], true, '');
    }

    public function testEdgeCasesInvalidContactFormat(): void
    {
        $privateKeyPem = $this->generateTestPrivateKey();
        $invalidContacts = ['invalid-email-format'];

        // 根据 ACME 协议，这应该由 API 客户端处理
        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'newAccount',
                ['contact' => $invalidContacts, 'termsOfServiceAgreed' => true],
                self::anything()
            )
            ->willThrowException(new AcmeValidationException('Invalid contact format'))
        ;

        $this->expectException(AbstractAcmeException::class);

        $this->service->registerAccount($invalidContacts, true, $privateKeyPem);
    }

    private function generateTestPrivateKey(): string
    {
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $privateKey) {
            throw new CertificateGenerationException('Failed to generate private key');
        }

        $privateKeyPem = '';
        $result = openssl_pkey_export($privateKey, $privateKeyPem);
        if (!$result || !is_string($privateKeyPem)) {
            throw new CertificateGenerationException('Failed to export private key');
        }

        return $privateKeyPem;
    }
}
