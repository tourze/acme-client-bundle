<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\AccountStatus;
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Enum\OrderStatus;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Repository\AccountRepository;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;
use Tourze\ACMEClientBundle\Repository\ChallengeRepository;
use Tourze\ACMEClientBundle\Repository\IdentifierRepository;
use Tourze\ACMEClientBundle\Repository\OrderRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\ChallengeService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ChallengeService::class)]
#[RunTestsInSeparateProcesses]
final class ChallengeServiceTest extends AbstractIntegrationTestCase
{
    private ChallengeService $service;

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

        $this->service = self::getService(ChallengeService::class);
    }

    public function testCompleteChallenge(): void
    {
        $challenge = $this->createChallenge();

        // Mock API 客户端调用
        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'valid'])
        ;

        $result = $this->service->completeChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::VALID, $challenge->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($challenge);
    }

    public function testGetDnsChallengeRecord(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setToken('test-token');

        $expectedRecord = '_acme-challenge.example.com';
        $expectedValue = 'test-validation-value';

        // getDnsChallengeRecord 是本地方法，不需要 Mock API
        $result = $this->service->getDnsChallengeRecord($challenge);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('type', $result);
    }

    public function testValidateChallenge(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setStatus(ChallengeStatus::PENDING);

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'processing'])
        ;

        $result = $this->service->validateChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::PROCESSING, $challenge->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($challenge);
    }

    public function testFindChallengesByStatus(): void
    {
        // 先创建一些测试挑战
        $challenge1 = $this->createChallenge();
        $challenge1->setStatus(ChallengeStatus::VALID);
        $repository = self::getService(ChallengeRepository::class);
        $this->assertInstanceOf(ChallengeRepository::class, $repository);
        $repository->save($challenge1);

        $challenge2 = $this->createChallenge();
        $challenge2->setStatus(ChallengeStatus::VALID);
        $repository = self::getService(ChallengeRepository::class);
        $this->assertInstanceOf(ChallengeRepository::class, $repository);
        $repository->save($challenge2);

        $result = $this->service->findChallengesByStatus(ChallengeStatus::VALID);
        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $challenge) {
            $this->assertEquals(ChallengeStatus::VALID, $challenge->getStatus());
        }
    }

    public function testFindChallengesByType(): void
    {
        // 先创建一些测试挑战
        $challenge1 = $this->createChallenge();
        $challenge1->setType(ChallengeType::DNS_01);
        $repository = self::getService(ChallengeRepository::class);
        $this->assertInstanceOf(ChallengeRepository::class, $repository);
        $repository->save($challenge1);

        $challenge2 = $this->createChallenge();
        $challenge2->setType(ChallengeType::DNS_01);
        $repository = self::getService(ChallengeRepository::class);
        $this->assertInstanceOf(ChallengeRepository::class, $repository);
        $repository->save($challenge2);

        $result = $this->service->findChallengesByType(ChallengeType::DNS_01);
        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $challenge) {
            $this->assertEquals(ChallengeType::DNS_01, $challenge->getType());
        }
    }

    public function testIsChallengeValid(): void
    {
        $challenge = new Challenge();
        $challenge->setStatus(ChallengeStatus::VALID);

        $isValid = $this->service->isChallengeValid($challenge);

        $this->assertTrue($isValid);
    }

    public function testIsChallengeValidWithInvalidStatus(): void
    {
        $challenge = new Challenge();
        $challenge->setStatus(ChallengeStatus::INVALID);

        $isValid = $this->service->isChallengeValid($challenge);

        $this->assertFalse($isValid);
    }

    public function testBusinessScenarioCompleteValidation(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::PENDING);
        $challenge->setToken('test-token');
        $challenge->setKeyAuthorization('test-key-auth');

        $entityManager = self::getEntityManager();

        $authorization = $challenge->getAuthorization();
        $this->assertNotNull($authorization, 'Authorization should not be null');

        $order = $authorization->getOrder();
        $this->assertNotNull($order, 'Order should not be null');

        $account = $order->getAccount();
        $this->assertNotNull($account, 'Account should not be null');

        $entityManager->persist($account);
        $entityManager->persist($order);
        $entityManager->persist($authorization);
        $entityManager->persist($challenge);
        $entityManager->flush();

        // Mock API client responses
        $this->apiClient->expects($this->atLeastOnce())
            ->method('post')
            ->willReturn(['status' => 'processing'])
        ;

        $this->apiClient->expects($this->any())
            ->method('get')
            ->willReturn(['status' => 'valid'])
        ;

        // Test the complete workflow
        $dnsRecord = $this->service->getDnsChallengeRecord($challenge);
        $this->assertArrayHasKey('name', $dnsRecord);
        $this->assertArrayHasKey('value', $dnsRecord);

        $completedChallenge = $this->service->completeChallenge($challenge);
        $this->assertInstanceOf(Challenge::class, $completedChallenge);

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($completedChallenge);
    }

    public function testEdgeCasesInvalidChallenge(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setStatus(ChallengeStatus::INVALID);

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Challenge must be in PENDING status to complete');

        $this->service->completeChallenge($challenge);
    }

    private function createChallenge(): Challenge
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setAccountUrl('https://acme.example.com/account/123');
        $account->setStatus(AccountStatus::VALID);
        $account->setValid(true);

        // 持久化 Account
        $accountRepository = self::getService(AccountRepository::class);
        $this->assertInstanceOf(AccountRepository::class, $accountRepository);
        $accountRepository->save($account);

        $order = new Order();
        $order->setAccount($account);
        $order->setOrderUrl('https://acme.example.com/order/' . uniqid());
        $order->setStatus(OrderStatus::PENDING);
        $order->setExpiresTime(new \DateTimeImmutable('+7 days'));

        // 持久化 Order
        $orderRepository = self::getService(OrderRepository::class);
        $this->assertInstanceOf(OrderRepository::class, $orderRepository);
        $orderRepository->save($order);

        $identifier = new Identifier();
        $identifier->setOrder($order);
        $identifier->setValue('example.com');
        $identifier->setType('dns');

        // 持久化 Identifier
        $identifierRepository = self::getService(IdentifierRepository::class);
        $this->assertInstanceOf(IdentifierRepository::class, $identifierRepository);
        $identifierRepository->save($identifier);

        $authorization = new Authorization();
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $authorization->setAuthorizationUrl('https://acme.example.com/authz/' . uniqid());
        $authorization->setStatus(AuthorizationStatus::PENDING);
        $authorization->setExpiresTime(new \DateTimeImmutable('+7 days'));

        // 持久化 Authorization
        $authorizationRepository = self::getService(AuthorizationRepository::class);
        $this->assertInstanceOf(AuthorizationRepository::class, $authorizationRepository);
        $authorizationRepository->save($authorization);

        $challenge = new Challenge();
        $challenge->setAuthorization($authorization);
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setStatus(ChallengeStatus::PENDING);
        $challenge->setToken('test-token-' . uniqid());
        $challenge->setUrl('https://acme.example.com/challenge/' . uniqid());
        $challenge->setKeyAuthorization('test-key-authorization-' . uniqid());

        return $challenge;
    }

    public function testCheckChallengeStatus(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setStatus(ChallengeStatus::PROCESSING);

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($challenge->getChallengeUrl())
            ->willReturn([
                'status' => 'valid',
                'validated' => '2024-01-01T00:00:00Z',
            ])
        ;

        $result = $this->service->checkChallengeStatus($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::VALID, $challenge->getStatus());
        $this->assertNotNull($challenge->getValidatedTime());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($challenge);
    }

    public function testCheckChallengeStatusWithError(): void
    {
        $challenge = $this->createChallenge();
        $errorDetails = ['type' => 'dns-01', 'detail' => 'DNS record not found'];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($challenge->getChallengeUrl())
            ->willReturn([
                'status' => 'invalid',
                'error' => $errorDetails,
            ])
        ;

        $result = $this->service->checkChallengeStatus($challenge);

        $this->assertEquals(ChallengeStatus::INVALID, $result->getStatus());
        $this->assertEquals($errorDetails, $result->getError());
    }

    public function testCheckChallengeStatusWithoutUrl(): void
    {
        $challenge = new Challenge();
        $challenge->setUrl('');

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Challenge URL not found');

        $this->service->checkChallengeStatus($challenge);
    }

    public function testCleanupDnsRecord(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setDnsRecordName('_acme-challenge.example.com');
        $challenge->setDnsRecordValue('test-value');

        // 这个方法目前只记录日志，所以我们只需要确保它不抛出异常
        $this->service->cleanupDnsRecord($challenge);

        // 验证方法执行没有抛出异常
        $this->assertSame('_acme-challenge.example.com', $challenge->getDnsRecordName());
        $this->assertSame('test-value', $challenge->getDnsRecordValue());
    }

    public function testCleanupDnsRecordWithoutRecordData(): void
    {
        $challenge = $this->createChallenge();
        // 不设置 DNS 记录名称和值

        // 这个方法应该能处理没有 DNS 记录数据的情况
        $this->service->cleanupDnsRecord($challenge);

        // 验证没有 DNS 记录数据的情况下方法也能正常执行
        $this->assertNull($challenge->getDnsRecordName());
        $this->assertNull($challenge->getDnsRecordValue());
    }

    public function testPrepareDnsChallenge(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setToken('test-token');

        $result = $this->service->prepareDnsChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::PENDING, $challenge->getStatus());
        // getKeyAuthorization() 返回类型为 string，getDnsRecordValue() 在此上下文已被缩窄为 string
        $this->assertNotEmpty($challenge->getKeyAuthorization());
        $this->assertNotEmpty($challenge->getDnsRecordValue());
        $this->assertEquals('_acme-challenge.example.com', $challenge->getDnsRecordName());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($challenge);
    }

    public function testPrepareDnsChallengeWithoutDomain(): void
    {
        $challenge = $this->createChallenge();

        $authorization = $challenge->getAuthorization();
        $this->assertNotNull($authorization, 'Authorization should not be null');

        $identifier = $authorization->getIdentifier();
        $this->assertNotNull($identifier, 'Identifier should not be null');

        $identifier->setValue('');

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Domain not found in authorization');

        $this->service->prepareDnsChallenge($challenge);
    }

    public function testPrepareDnsChallengeWithoutToken(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setToken('');

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Challenge token not found');

        $this->service->prepareDnsChallenge($challenge);
    }

    public function testRespondToChallenge(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setStatus(ChallengeStatus::PENDING);

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                $challenge->getChallengeUrl(),
                [],
                self::anything(),
                self::anything()
            )
            ->willReturn(['status' => 'processing'])
        ;

        $result = $this->service->respondToChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::PROCESSING, $challenge->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($challenge);
    }

    public function testRespondToChallengeWithValidated(): void
    {
        $challenge = $this->createChallenge();
        $validatedTime = '2024-01-01T00:00:00Z';

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 'valid',
                'validated' => $validatedTime,
            ])
        ;

        $result = $this->service->respondToChallenge($challenge);

        $this->assertEquals(ChallengeStatus::VALID, $result->getStatus());
        $this->assertNotNull($result->getValidatedTime());
    }

    public function testRespondToChallengeWithError(): void
    {
        $challenge = $this->createChallenge();
        $errorDetails = ['type' => 'serverInternal', 'detail' => 'Internal server error'];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 'invalid',
                'error' => $errorDetails,
            ])
        ;

        $result = $this->service->respondToChallenge($challenge);

        $this->assertEquals(ChallengeStatus::INVALID, $result->getStatus());
        $this->assertEquals($errorDetails, $result->getError());
    }

    public function testRespondToChallengeWithInvalidAccountData(): void
    {
        $challenge = $this->createChallenge();

        $authorization = $challenge->getAuthorization();
        $this->assertNotNull($authorization, 'Authorization should not be null');

        $order = $authorization->getOrder();
        $this->assertNotNull($order, 'Order should not be null');

        $account = $order->getAccount();
        $this->assertNotNull($account, 'Account should not be null');

        $account->setPrivateKey('invalid-key');

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Invalid challenge or account data');

        $this->service->respondToChallenge($challenge);
    }

    public function testSetupDnsRecord(): void
    {
        $challenge = $this->createChallenge();
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setToken('test-token');

        $this->service->setupDnsRecord($challenge);

        // 验证 DNS 记录已设置
        $this->assertNotNull($challenge->getDnsRecordName());
        $this->assertNotNull($challenge->getDnsRecordValue());
        $this->assertEquals(ChallengeStatus::PENDING, $challenge->getStatus());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($challenge);
    }

    public function testStartChallenge(): void
    {
        $challenge = $this->createChallenge();

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'processing'])
        ;

        $result = $this->service->startChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::PROCESSING, $challenge->getStatus());
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

        $result = openssl_pkey_export($privateKey, $privateKeyPem);
        if (!$result) {
            throw new CertificateGenerationException('Failed to export private key');
        }

        return $privateKeyPem;
    }
}
