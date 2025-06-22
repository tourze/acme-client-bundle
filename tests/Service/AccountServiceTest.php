<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;

/**
 * AccountService 测试
 */
class AccountServiceTest extends TestCase
{
    private AccountService $service;
    
    /** @var AcmeApiClient */
    private $apiClient;
    
    /** @var EntityManagerInterface */
    private $entityManager;
    
    /** @var AccountRepository */
    private $accountRepository;
    
    /** @var LoggerInterface */
    private $logger;
    
    private string $acmeServerUrl = 'https://acme-v02.api.letsencrypt.org/directory';

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(AcmeApiClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->accountRepository = $this->createMock(AccountRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->service = new AccountService(
            $this->apiClient,
            $this->entityManager,
            $this->accountRepository,
            $this->logger,
            $this->acmeServerUrl
        );
    }

    public function testConstructor(): void
    {
        $service = new AccountService(
            $this->apiClient,
            $this->entityManager,
            $this->accountRepository,
            $this->logger,
            $this->acmeServerUrl
        );
        $this->assertInstanceOf(AccountService::class, $service);
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
            ->with('newAccount', $this->anything(), $this->anything())
            ->willReturn($mockResponse);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Account::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('ACME account registered successfully', $this->anything());

        $result = $this->service->registerAccount($contacts, true, $privateKeyPem);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertEquals($contacts, $result->getContacts());
        $this->assertEquals(AccountStatus::VALID, $result->getStatus());
        $this->assertEquals($this->acmeServerUrl, $result->getAcmeServerUrl());
        $this->assertTrue($result->isTermsOfServiceAgreed());
    }

    public function testRegisterAccountWithGeneratedKey(): void
    {
        $contacts = ['mailto:test@example.com'];
        
        $mockResponse = ['status' => 'valid'];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn($mockResponse);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->registerAccount($contacts);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertNotNull($result->getPrivateKey());
    }

    public function testRegisterAccountWithInvalidPrivateKey(): void
    {
        $contacts = ['mailto:test@example.com'];
        $invalidKey = 'invalid-key';

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Invalid private key provided');

        $this->service->registerAccount($contacts, true, $invalidKey);
    }

    public function testRegisterAccountApiFailure(): void
    {
        $contacts = ['mailto:test@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();
        
        $this->apiClient->expects($this->once())
            ->method('post')
            ->willThrowException(new \RuntimeException('API Error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to register ACME account', $this->anything());

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Account registration failed: API Error');

        $this->service->registerAccount($contacts, true, $privateKeyPem);
    }

    public function testGetAccountInfo(): void
    {
        $account = $this->createTestAccount();
        $expectedResponse = ['status' => 'valid', 'contact' => ['mailto:test@example.com']];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with($account->getAccountUrl(), [], $this->anything(), $account->getAccountUrl())
            ->willReturn($expectedResponse);

        $result = $this->service->getAccountInfo($account);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetAccountInfoWithInvalidKey(): void
    {
        $account = new Account();
        $account->setPrivateKey('invalid-key');
        $account->setAccountUrl('https://example.com/account');

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Invalid account private key');

        $this->service->getAccountInfo($account);
    }

    public function testGetAccountInfoWithoutUrl(): void
    {
        $account = new Account();
        $account->setPrivateKey($this->generateTestPrivateKey());

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Account URL not found');

        $this->service->getAccountInfo($account);
    }

    public function testUpdateAccount(): void
    {
        $account = $this->createTestAccount();
        $newContacts = ['mailto:new@example.com'];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with($account->getAccountUrl(), ['contact' => $newContacts], $this->anything(), $account->getAccountUrl())
            ->willReturn(['status' => 'valid']);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($account);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('ACME account updated successfully', $this->anything());

        $result = $this->service->updateAccount($account, $newContacts);

        $this->assertEquals($newContacts, $result->getContacts());
    }

    public function testUpdateAccountFailure(): void
    {
        $account = $this->createTestAccount();
        $newContacts = ['mailto:new@example.com'];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willThrowException(new \RuntimeException('Update failed'));

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Account update failed: Update failed');

        $this->service->updateAccount($account, $newContacts);
    }

    public function testDeactivateAccount(): void
    {
        $account = $this->createTestAccount();

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with($account->getAccountUrl(), ['status' => 'deactivated'], $this->anything(), $account->getAccountUrl())
            ->willReturn(['status' => 'deactivated']);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($account);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->deactivateAccount($account);

        $this->assertEquals(AccountStatus::DEACTIVATED, $result->getStatus());
    }

    public function testFindAccountsByServerUrl(): void
    {
        $serverUrl = 'https://acme-staging-v02.api.letsencrypt.org/directory';
        $expectedAccounts = [new Account(), new Account()];

        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->with(['acmeServerUrl' => $serverUrl])
            ->willReturn($expectedAccounts);

        $result = $this->service->findAccountsByServerUrl($serverUrl);

        $this->assertEquals($expectedAccounts, $result);
    }

    public function testFindAccountsByStatus(): void
    {
        $status = AccountStatus::VALID;
        $expectedAccounts = [new Account()];

        $this->accountRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => $status])
            ->willReturn($expectedAccounts);

        $result = $this->service->findAccountsByStatus($status);

        $this->assertEquals($expectedAccounts, $result);
    }

    public function testIsAccountValid(): void
    {
        $validAccount = new Account();
        $validAccount->setStatus(AccountStatus::VALID);

        $invalidAccount = new Account();
        $invalidAccount->setStatus(AccountStatus::DEACTIVATED);

        $this->assertTrue($this->service->isAccountValid($validAccount));
        $this->assertFalse($this->service->isAccountValid($invalidAccount));
    }


    public function testGetEmailFromAccount(): void
    {
        $account = new Account();
        $account->setContacts(['mailto:test@example.com', 'mailto:admin@example.com']);

        $result = $this->service->getEmailFromAccount($account);

        $this->assertEquals('test@example.com', $result);
    }

    public function testGetEmailFromAccountNoEmails(): void
    {
        $account = new Account();
        $account->setContacts(['tel:+1234567890']);

        $result = $this->service->getEmailFromAccount($account);

        $this->assertNull($result);
    }

    public function testGetEmailFromAccountEmptyContacts(): void
    {
        $account = new Account();
        $account->setContacts([]);

        $result = $this->service->getEmailFromAccount($account);

        $this->assertNull($result);
    }

    public function testGetAccountById(): void
    {
        $id = 123;
        $expectedAccount = new Account();

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($expectedAccount);

        $result = $this->service->getAccountById($id);

        $this->assertEquals($expectedAccount, $result);
    }

    public function testBusinessScenarioAccountLifecycle(): void
    {
        // 注册账户
        $contacts = ['mailto:lifecycle@example.com'];
        $privateKeyPem = $this->generateTestPrivateKey();
        
        $this->apiClient->expects($this->exactly(3))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'valid'],           // 注册
                ['status' => 'valid'],           // 更新
                ['status' => 'deactivated']      // 停用
            );

        $this->entityManager->expects($this->exactly(3))
            ->method('persist');

        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 注册
        $account = $this->service->registerAccount($contacts, true, $privateKeyPem);
        $account->setAccountUrl('https://example.com/account/123'); // 模拟设置URL

        // 更新
        $newContacts = ['mailto:updated@example.com'];
        $updatedAccount = $this->service->updateAccount($account, $newContacts);

        // 停用
        $deactivatedAccount = $this->service->deactivateAccount($updatedAccount);

        $this->assertEquals($newContacts, $updatedAccount->getContacts());
        $this->assertEquals(AccountStatus::DEACTIVATED, $deactivatedAccount->getStatus());
    }

    public function testEdgeCasesInvalidInputs(): void
    {
        // 空联系方式
        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'valid']);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->registerAccount([]);
        $this->assertEquals([], $result->getContacts());
    }

    private function generateTestPrivateKey(): string
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        openssl_pkey_export($privateKey, $privateKeyPem);

        return $privateKeyPem;
    }

    private function createTestAccount(): Account
    {
        $account = new Account();
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setAccountUrl('https://example.com/account/123');
        $account->setContacts(['mailto:test@example.com']);
        $account->setStatus(AccountStatus::VALID);

        return $account;
    }
} 