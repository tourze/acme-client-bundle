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
use Tourze\ACMEClientBundle\Enum\AuthorizationStatus;
use Tourze\ACMEClientBundle\Enum\ChallengeType;
use Tourze\ACMEClientBundle\Exception\AbstractAcmeException;
use Tourze\ACMEClientBundle\Exception\CertificateGenerationException;
use Tourze\ACMEClientBundle\Repository\AuthorizationRepository;
use Tourze\ACMEClientBundle\Service\AcmeApiClient;
use Tourze\ACMEClientBundle\Service\AuthorizationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AuthorizationService::class)]
#[RunTestsInSeparateProcesses]
final class AuthorizationServiceTest extends AbstractIntegrationTestCase
{
    private AuthorizationService $service;

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

        $this->service = self::getService(AuthorizationService::class);
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
                        'status' => 'pending',
                    ],
                ],
            ])
        ;

        // 使用真实的 EntityManager，不需要设置 mock 期望

        $result = $this->service->fetchAuthorizationDetails($authorization);

        $this->assertSame($authorization, $result);
        $this->assertEquals(AuthorizationStatus::VALID, $authorization->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $authorization->getExpiresTime());

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($authorization);
    }

    public function testFetchAuthorizationDetailsWithoutUrl(): void
    {
        $authorization = $this->createAuthorization();
        // 设置空的 authorizationUrl 来触发异常
        $authorization->setAuthorizationUrl('');

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Invalid authorization or account data');

        $this->service->fetchAuthorizationDetails($authorization);
    }

    public function testFindAuthorizationByDomain(): void
    {
        // 先创建一个测试的 Authorization 实体
        $authorization = $this->createAuthorization();
        $identifier = $authorization->getIdentifier();
        $this->assertNotNull($identifier, 'Identifier should not be null');
        $identifier->setValue('example.com');
        $repository = self::getService(AuthorizationRepository::class);
        $this->assertInstanceOf(AuthorizationRepository::class, $repository);
        $repository->save($authorization);

        $result = $this->service->findAuthorizationByDomain('example.com');

        $this->assertNotNull($result);
        $this->assertEquals('example.com', $result->getIdentifierValue());
    }

    public function testFindAuthorizationByDomainNotFound(): void
    {
        $result = $this->service->findAuthorizationByDomain('notfound.com');

        $this->assertNull($result);
    }

    public function testFindAuthorizationsByStatus(): void
    {
        // 直接从 repository 创建一些测试数据
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setAccountUrl('https://acme.example.com/account/123');

        $order1 = new Order();
        $order1->setAccount($account);
        $order1->setOrderUrl('https://acme.example.com/order/' . uniqid());

        $identifier1 = new Identifier();
        $identifier1->setOrder($order1);
        $identifier1->setValue('example1.com');

        $authorization1 = new Authorization();
        $authorization1->setOrder($order1);
        $authorization1->setIdentifier($identifier1);
        $authorization1->setAuthorizationUrl('https://acme.example.com/authz/' . uniqid());
        $authorization1->setStatus(AuthorizationStatus::VALID);

        $order2 = new Order();
        $order2->setAccount($account);
        $order2->setOrderUrl('https://acme.example.com/order/' . uniqid());

        $identifier2 = new Identifier();
        $identifier2->setOrder($order2);
        $identifier2->setValue('example2.com');

        $authorization2 = new Authorization();
        $authorization2->setOrder($order2);
        $authorization2->setIdentifier($identifier2);
        $authorization2->setAuthorizationUrl('https://acme.example.com/authz/' . uniqid());
        $authorization2->setStatus(AuthorizationStatus::VALID);

        // 使用 EntityManager 直接保存（简化测试）
        self::getEntityManager()->persist($account);
        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($identifier1);
        self::getEntityManager()->persist($authorization1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->persist($identifier2);
        self::getEntityManager()->persist($authorization2);
        self::getEntityManager()->flush();

        $result = $this->service->findAuthorizationsByStatus(AuthorizationStatus::VALID);
        $this->assertGreaterThanOrEqual(2, count($result));
        foreach ($result as $auth) {
            $this->assertEquals(AuthorizationStatus::VALID, $auth->getStatus());
        }
    }

    public function testIsAuthorizationValid(): void
    {
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::VALID);
        $authorization->setExpiresTime(new \DateTimeImmutable('+1 day'));

        $isValid = $this->service->isAuthorizationValid($authorization);

        $this->assertTrue($isValid);
    }

    public function testIsAuthorizationValidWithExpiredAuth(): void
    {
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::VALID);
        $authorization->setExpiresTime(new \DateTimeImmutable('-1 day'));

        $isValid = $this->service->isAuthorizationValid($authorization);

        $this->assertFalse($isValid);
    }

    public function testIsAuthorizationValidWithInvalidStatus(): void
    {
        $authorization = new Authorization();
        $authorization->setStatus(AuthorizationStatus::INVALID);
        $authorization->setExpiresTime(new \DateTimeImmutable('+1 day'));

        $isValid = $this->service->isAuthorizationValid($authorization);

        $this->assertFalse($isValid);
    }

    public function testIsAuthorizationExpired(): void
    {
        $authorization = new Authorization();
        $authorization->setExpiresTime(new \DateTimeImmutable('-1 day'));

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
        $authorization->setExpiresTime(new \DateTimeImmutable('+1 day'));

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
                self::anything(),
                'https://acme.example.com/account/123'
            )
            ->willReturn(['status' => 'invalid'])
        ;

        // 使用真实的 EntityManager，不需要设置 mock 期望

        $result = $this->service->deactivateAuthorization($authorization);

        $this->assertSame($authorization, $result);

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($authorization);
    }

    public function testDeactivateAuthorizationWithoutUrl(): void
    {
        $authorization = $this->createAuthorization();
        // 设置空的 authorizationUrl 来触发异常
        $authorization->setAuthorizationUrl('');

        $this->expectException(AbstractAcmeException::class);
        $this->expectExceptionMessage('Invalid authorization or account data');

        $this->service->deactivateAuthorization($authorization);
    }

    private function createAuthorization(): Authorization
    {
        $account = new Account();
        $account->setAcmeServerUrl('https://acme.example.com');
        $account->setPrivateKey($this->generateTestPrivateKey());
        $account->setPublicKeyJwk('{"kty":"RSA","use":"sig","kid":"test-key","n":"test","e":"AQAB"}');
        $account->setAccountUrl('https://acme.example.com/account/123');

        $order = new Order();
        $order->setAccount($account);
        $order->setOrderUrl('https://acme.example.com/order/' . uniqid());

        $identifier = new Identifier();
        $identifier->setOrder($order);
        $identifier->setValue('example.com');

        $authorization = new Authorization();
        $authorization->setOrder($order);
        $authorization->setIdentifier($identifier);
        $authorization->setAuthorizationUrl('https://acme.example.com/authz/' . uniqid());

        return $authorization;
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
