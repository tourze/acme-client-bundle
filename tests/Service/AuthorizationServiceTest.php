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
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\AuthorizationService;

class AuthorizationServiceTest extends TestCase
{
    private AuthorizationService $service;
    private MockObject&AcmeApiClient $apiClient;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&AuthorizationRepository $repository;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(AcmeApiClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(AuthorizationRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new AuthorizationService(
            $this->apiClient,
            $this->entityManager,
            $this->repository,
            $this->logger
        );
    }

    public function testFetchAuthorizationDetails(): void
    {
        $authorization = $this->createAuthorization();
        $authorization->setAuthorizationUrl('https://acme.example.com/authz/123');

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with('https://acme.example.com/authz/123')
            ->willReturn([
                'status' => 'valid',
                'expires' => '2023-12-31T23:59:59Z',
                'challenges' => [
                    [
                        'type' => 'dns-01',
                        'url' => 'https://acme.example.com/challenge/123',
                        'token' => 'test-token',
                        'status' => 'pending'
                    ]
                ]
            ]);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->fetchAuthorizationDetails($authorization);

        $this->assertSame($authorization, $result);
        $this->assertEquals(AuthorizationStatus::VALID, $authorization->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $authorization->getExpiresTime());
    }

    public function testFetchAuthorizationDetailsWithoutUrl(): void
    {
        $authorization = $this->createAuthorization();
        // 使用反射来模拟未设置 URL 的情况
        $reflection = new \ReflectionClass($authorization);
        $property = $reflection->getProperty('authorizationUrl');
        $property->setAccessible(true);
        // 不设置值，保持未初始化状态

        $this->expectException(\Error::class);

        $this->service->fetchAuthorizationDetails($authorization);
    }

    public function testFindAuthorizationByDomain(): void
    {
        $authorization = new Authorization();

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['domain' => 'example.com'])
            ->willReturn($authorization);

        $result = $this->service->findAuthorizationByDomain('example.com');

        $this->assertSame($authorization, $result);
    }

    public function testFindAuthorizationByDomainNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['domain' => 'notfound.com'])
            ->willReturn(null);

        $result = $this->service->findAuthorizationByDomain('notfound.com');

        $this->assertNull($result);
    }

    public function testFindAuthorizationsByStatus(): void
    {
        $authorizations = [new Authorization(), new Authorization()];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['status' => AuthorizationStatus::VALID])
            ->willReturn($authorizations);

        $result = $this->service->findAuthorizationsByStatus(AuthorizationStatus::VALID);

        $this->assertSame($authorizations, $result);
    }

    public function testIsAuthorizationValid(): void
    {
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::VALID);
        $authorization->setExpiresTime(new DateTimeImmutable('+1 day'));

        $isValid = $this->service->isAuthorizationValid($authorization);

        $this->assertTrue($isValid);
    }

    public function testIsAuthorizationValidWithExpiredAuth(): void
    {
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::VALID);
        $authorization->setExpiresTime(new DateTimeImmutable('-1 day'));

        $isValid = $this->service->isAuthorizationValid($authorization);

        $this->assertFalse($isValid);
    }

    public function testIsAuthorizationValidWithInvalidStatus(): void
    {
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::INVALID);
        $authorization->setExpiresTime(new DateTimeImmutable('+1 day'));

        $isValid = $this->service->isAuthorizationValid($authorization);

        $this->assertFalse($isValid);
    }

    public function testIsAuthorizationExpired(): void
    {
        $authorization = new Authorization();
        $authorization->setExpiresTime(new DateTimeImmutable('-1 day'));

        $isExpired = $this->service->isAuthorizationExpired($authorization);

        $this->assertTrue($isExpired);
    }

    public function testIsAuthorizationExpiredWithNullExpiry(): void
    {
        $authorization = new Authorization();
        $authorization->setExpiresTime(null);

        $isExpired = $this->service->isAuthorizationExpired($authorization);

        $this->assertFalse($isExpired);
    }

    public function testIsAuthorizationExpiredWithFutureDate(): void
    {
        $authorization = new Authorization();
        $authorization->setExpiresTime(new DateTimeImmutable('+1 day'));

        $isExpired = $this->service->isAuthorizationExpired($authorization);

        $this->assertFalse($isExpired);
    }

    public function testGetDnsChallenge(): void
    {
        $authorization = new Authorization();
        $challenge = new Challenge();
        $challenge->setType(ChallengeType::DNS_01);
        $authorization->addChallenge($challenge);

        $result = $this->service->getDnsChallenge($authorization);

        $this->assertSame($challenge, $result);
    }

    public function testGetDnsChallengeNotFound(): void
    {
        $authorization = new Authorization();

        $result = $this->service->getDnsChallenge($authorization);

        $this->assertNull($result);
    }

    public function testDeactivateAuthorization(): void
    {
        $authorization = $this->createAuthorization();
        $authorization->setAuthorizationUrl('https://acme.example.com/authz/123');

        $this->apiClient->expects($this->once())
            ->method('post')
            ->with(
                'https://acme.example.com/authz/123',
                ['status' => 'deactivated'],
                $this->anything(),
                'https://acme.example.com/account/123'
            )
                         ->willReturn(['status' => 'invalid']);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($authorization);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->deactivateAuthorization($authorization);

        $this->assertSame($authorization, $result);
    }

    public function testDeactivateAuthorizationWithoutUrl(): void
    {
        $authorization = $this->createAuthorization();
        // 使用反射来模拟未设置 URL 的情况
        $reflection = new \ReflectionClass($authorization);
        $property = $reflection->getProperty('authorizationUrl');
        $property->setAccessible(true);
        // 不设置值，保持未初始化状态

        $this->expectException(\Error::class);

        $this->service->deactivateAuthorization($authorization);
    }

    /**
     * 创建测试用的授权
     */
    private function createAuthorization(): Authorization
    {
        $account = new Account();
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setAccountUrl('https://acme.example.com/account/123');

        $identifier = new Identifier();
        $identifier->setValue('example.com');

        $order = new Order();
        $order->setAccount($account);

        $authorization = new Authorization();
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);

        return $authorization;
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