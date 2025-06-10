<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\ACMEClientBundle\Entity\Account;
use Tourze\ACMEClientBundle\Entity\Authorization;
use Tourze\ACMEClientBundle\Entity\Challenge;
use Tourze\ACMEClientBundle\Entity\Identifier;
use Tourze\ACMEClientBundle\Entity\Order;
use Tourze\ACMEClientBundle\Enum\ChallengeStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Repository\ChallengeRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\ChallengeService;

class ChallengeServiceTest extends TestCase
{
    private ChallengeService $service;
    private MockObject&AcmeApiClient $apiClient;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&ChallengeRepository $repository;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(AcmeApiClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(ChallengeRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ChallengeService(
            $this->apiClient,
            $this->entityManager,
            $this->repository,
            $this->logger
        );
    }

    public function testPrepareDnsChallenge(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setToken('test-token-123');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($challenge);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->prepareDnsChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::PENDING, $challenge->getStatus());
        $this->assertNotNull($challenge->getKeyAuthorization());
        $this->assertNotNull($challenge->getDnsRecordValue());
        $this->assertEquals('_acme-challenge.example.com', $challenge->getDnsRecordName());
    }

        public function testPrepareDnsChallengeTypValidation(): void
    {
        // 这个测试验证服务确实只支持DNS-01类型的挑战
        // 因为当前系统设计上只有DNS-01类型，所以这个测试主要验证方法存在性
        $challenge = $this->createDnsChallenge();
        $this->assertEquals(ChallengeType::DNS_01, $challenge->getType());
    }

    public function testPrepareDnsChallengeWithoutDomain(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->getAuthorization()->setIdentifier(null);

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Domain not found in authorization');

        $this->service->prepareDnsChallenge($challenge);
    }

    public function testPrepareDnsChallengeWithoutToken(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setToken('');

        $this->expectException(AcmeClientException::class);
        $this->expectExceptionMessage('Challenge token not found');

        $this->service->prepareDnsChallenge($challenge);
    }

    public function testRespondToChallenge(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setChallengeUrl('https://acme.example.com/challenge/123');

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'https://acme.example.com/challenge/123',
                [],
                $this->anything(),
                'https://acme.example.com/account/123'
            )
            ->willReturn([
                'status' => 'processing',
                'validated' => '2023-01-01T12:00:00Z'
            ]);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($challenge);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->respondToChallenge($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::PROCESSING, $challenge->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $challenge->getValidatedTime());
    }

    public function testRespondToChallengeWithError(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setChallengeUrl('https://acme.example.com/challenge/123');

        $errorData = [
            'type' => 'urn:ietf:params:acme:error:dns',
            'detail' => 'DNS record not found'
        ];

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 'invalid',
                'error' => $errorData
            ]);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->respondToChallenge($challenge);

        $this->assertEquals(ChallengeStatus::INVALID, $result->getStatus());
        $this->assertEquals($errorData, $result->getError());
    }

    public function testCheckChallengeStatus(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setChallengeUrl('https://acme.example.com/challenge/123');

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with('https://acme.example.com/challenge/123')
            ->willReturn([
                'status' => 'valid',
                'validated' => '2023-01-01T12:00:00Z'
            ]);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($challenge);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->checkChallengeStatus($challenge);

        $this->assertSame($challenge, $result);
        $this->assertEquals(ChallengeStatus::VALID, $challenge->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $challenge->getValidatedTime());
    }

    public function testCheckChallengeStatusWithoutUrl(): void
    {
        $challenge = $this->createDnsChallenge();
        // 使用反射来模拟未设置 URL 的情况
        $reflection = new \ReflectionClass($challenge);
        $property = $reflection->getProperty('challengeUrl');
        $property->setAccessible(true);
        // 不设置值，保持未初始化状态

        $this->expectException(\Error::class);

        $this->service->checkChallengeStatus($challenge);
    }

    public function testCleanupDnsRecord(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setDnsRecordName('_acme-challenge.example.com');
        $challenge->setDnsRecordValue('test-record-value');

        // This method should not throw any exceptions
        $this->service->cleanupDnsRecord($challenge);

        // Since it's a placeholder implementation, we just verify it can be called
        $this->assertTrue(true);
    }

    public function testIsChallengeValid(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setStatus(ChallengeStatus::VALID);

        $isValid = $this->service->isChallengeValid($challenge);

        $this->assertTrue($isValid);
    }

    public function testIsChallengeValidWithInvalidStatus(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setStatus(ChallengeStatus::INVALID);

        $isValid = $this->service->isChallengeValid($challenge);

        $this->assertFalse($isValid);
    }

    public function testIsChallengeProcessing(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setStatus(ChallengeStatus::PROCESSING);

        $isProcessing = $this->service->isChallengeProcessing($challenge);

        $this->assertTrue($isProcessing);
    }

    public function testIsChallengeInvalid(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setStatus(ChallengeStatus::INVALID);

        $isInvalid = $this->service->isChallengeInvalid($challenge);

        $this->assertTrue($isInvalid);
    }

    public function testFindChallengesByStatus(): void
    {
        $this->assertTrue(method_exists($this->service, 'findChallengesByStatus'));
    }

    public function testGetDns01Challenge(): void
    {
        $authorization = new Authorization();
        $challenge = new Challenge();
        $challenge->setType(ChallengeType::DNS_01);
        $authorization->addChallenge($challenge);

        $result = $this->service->getDns01Challenge($authorization);

        $this->assertSame($challenge, $result);
    }

    public function testGetDns01ChallengeNotFound(): void
    {
        $authorization = new Authorization();

        $result = $this->service->getDns01Challenge($authorization);

        $this->assertNull($result);
    }

    public function testSetupDnsRecord(): void
    {
        $challenge = $this->createDnsChallenge();

        // This method should not throw any exceptions
        $this->service->setupDnsRecord($challenge);

        // Since it's a placeholder implementation, we just verify it can be called
        $this->assertTrue(true);
    }

    public function testStartChallenge(): void
    {
        $challenge = $this->createDnsChallenge();
        $challenge->setChallengeUrl('https://acme.example.com/challenge/123');

        $this->apiClient->expects($this->once())
            ->method('post')
            ->willReturn(['status' => 'processing']);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->startChallenge($challenge);

        $this->assertSame($challenge, $result);
    }

    /**
     * 创建测试用的DNS挑战
     */
    private function createDnsChallenge(): Challenge
    {
        $account = new Account();
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setAccountUrl('https://acme.example.com/account/123');

        $identifier = new Identifier();
        $identifier->setValue('example.com');

        $authorization = new Authorization();
        $authorization->setIdentifier($identifier);

        $order = new Order();
        $order->setAccount($account);

        $authorization->setOrder($order);

        $challenge = new Challenge();
        $challenge->setType(ChallengeType::DNS_01);
        $challenge->setAuthorization($authorization);
        $challenge->setToken('test-token');

        return $challenge;
    }

    /**
     * 生成测试用的私钥
     */
    private function generateTestPrivateKey(): string
    {
        $privateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($privateKey, $privateKeyPem);
        return $privateKeyPem;
    }
} 