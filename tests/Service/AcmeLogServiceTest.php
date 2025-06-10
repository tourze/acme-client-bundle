<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Service\AcmeLogService;

/**
 * AcmeLogService 测试
 */
class AcmeLogServiceTest extends TestCase
{
    private AcmeLogService $service;
    
    /** @var EntityManagerInterface */
    private $entityManager;
    
    /** @var EntityRepository */
    private $repository;
    
    /** @var QueryBuilder */
    private $queryBuilder;
    
    /** @var Query */
    private $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);
        
        $this->service = new AcmeLogService($this->entityManager);
    }

    public function testConstructor(): void
    {
        $service = new AcmeLogService($this->entityManager);
        $this->assertInstanceOf(AcmeLogService::class, $service);
    }

    public function testLogAccountOperation(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logAccountOperation(
            'register',
            'Account registered successfully',
            123,
            ['email' => 'test@example.com'],
            LogLevel::INFO
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('account_register', $result->getOperation());
        $this->assertEquals('Account registered successfully', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertEquals('Account', $result->getEntityType());
        $this->assertEquals(123, $result->getEntityId());
    }

    public function testLogAccountOperationWithDefaults(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logAccountOperation(
            'create',
            'Account created'
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('account_create', $result->getOperation());
        $this->assertEquals('Account created', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertEquals('Account', $result->getEntityType());
        $this->assertNull($result->getEntityId());
    }

    public function testLogOrderOperation(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logOrderOperation(
            'create',
            'Order created successfully',
            456,
            ['domains' => ['example.com']],
            LogLevel::DEBUG
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('order_create', $result->getOperation());
        $this->assertEquals('Order created successfully', $result->getMessage());
        $this->assertEquals(LogLevel::DEBUG, $result->getLevel());
        $this->assertEquals('Order', $result->getEntityType());
        $this->assertEquals(456, $result->getEntityId());
    }

    public function testLogChallengeOperation(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logChallengeOperation(
            'validate',
            'Challenge validated',
            789,
            ['type' => 'dns-01'],
            LogLevel::WARNING
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('challenge_validate', $result->getOperation());
        $this->assertEquals('Challenge validated', $result->getMessage());
        $this->assertEquals(LogLevel::WARNING, $result->getLevel());
        $this->assertEquals('Challenge', $result->getEntityType());
        $this->assertEquals(789, $result->getEntityId());
    }

    public function testLogCertificateOperation(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logCertificateOperation(
            'issue',
            'Certificate issued',
            101,
            ['serial' => 'ABC123'],
            LogLevel::ERROR
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('certificate_issue', $result->getOperation());
        $this->assertEquals('Certificate issued', $result->getMessage());
        $this->assertEquals(LogLevel::ERROR, $result->getLevel());
        $this->assertEquals('Certificate', $result->getEntityType());
        $this->assertEquals(101, $result->getEntityId());
    }

    public function testLogOperation(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logOperation(
            'custom_operation',
            'Custom operation performed',
            'CustomEntity',
            999,
            ['key' => 'value'],
            LogLevel::INFO
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('custom_operation', $result->getOperation());
        $this->assertEquals('Custom operation performed', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertEquals('CustomEntity', $result->getEntityType());
        $this->assertEquals(999, $result->getEntityId());
        $this->assertEquals(['key' => 'value'], $result->getContext());
    }

    public function testLogOperationWithDefaults(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logOperation(
            'simple_operation',
            'Simple operation'
        );

        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertEquals('simple_operation', $result->getOperation());
        $this->assertEquals('Simple operation', $result->getMessage());
        $this->assertEquals(LogLevel::INFO, $result->getLevel());
        $this->assertNull($result->getEntityType());
        $this->assertNull($result->getEntityId());
        $this->assertNull($result->getContext());
    }

    public function testLogException(): void
    {
        $exception = new \RuntimeException('Test exception', 500);
        
        // 由于 logException 方法内部创建了 AcmeExceptionService，
        // 我们只能验证 EntityManager 被调用了
        $this->entityManager->expects($this->once())
            ->method('persist');
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->logException(
            $exception,
            'TestEntity',
            123,
            ['context' => 'test']
        );
    }

    public function testLogExceptionWithDefaults(): void
    {
        $exception = new \Exception('Simple exception');
        
        $this->entityManager->expects($this->once())
            ->method('persist');
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->logException($exception);
    }

    public function testFindLogsWithAllFilters(): void
    {
        $expectedLogs = [
            new AcmeOperationLog(),
            new AcmeOperationLog(),
        ];

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AcmeOperationLog::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('l')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('l.occurredTime', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(50)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(4))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(4))
            ->method('setParameter')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedLogs);

        $result = $this->service->findLogs(
            'test_operation',
            'TestEntity',
            123,
            'info',
            50
        );

        $this->assertEquals($expectedLogs, $result);
    }

    public function testFindLogsWithDefaults(): void
    {
        $expectedLogs = [];

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AcmeOperationLog::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('l')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('l.occurredTime', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(100)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->never())
            ->method('andWhere');

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedLogs);

        $result = $this->service->findLogs();

        $this->assertEquals($expectedLogs, $result);
    }

    public function testFindLogsWithPartialFilters(): void
    {
        $expectedLogs = [new AcmeOperationLog()];

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AcmeOperationLog::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('l')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('l.occurredTime', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(100)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedLogs);

        $result = $this->service->findLogs(
            'test_operation',
            'TestEntity'
        );

        $this->assertEquals($expectedLogs, $result);
    }

    public function testCleanupOldLogs(): void
    {
        $deletedCount = 5;

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('delete')
            ->with(AcmeOperationLog::class, 'l')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('l.occurredTime < :cutoffDate')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('cutoffDate', $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('execute')
            ->willReturn($deletedCount);

        $result = $this->service->cleanupOldLogs(30);

        $this->assertEquals($deletedCount, $result);
    }

    public function testCleanupOldLogsWithCustomDays(): void
    {
        $deletedCount = 10;

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('delete')
            ->with(AcmeOperationLog::class, 'l')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('l.occurredTime < :cutoffDate')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('cutoffDate', $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('execute')
            ->willReturn($deletedCount);

        $result = $this->service->cleanupOldLogs(7);

        $this->assertEquals($deletedCount, $result);
    }

    public function testBusinessScenarioAccountLifecycle(): void
    {
        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 账户注册
        $registerLog = $this->service->logAccountOperation(
            'register',
            'Account registration started',
            null,
            ['email' => 'user@example.com']
        );

        // 账户激活
        $activateLog = $this->service->logAccountOperation(
            'activate',
            'Account activated successfully',
            123,
            ['status' => 'valid']
        );

        // 账户停用
        $deactivateLog = $this->service->logAccountOperation(
            'deactivate',
            'Account deactivated',
            123,
            ['reason' => 'user_request'],
            LogLevel::WARNING
        );

        $this->assertEquals('account_register', $registerLog->getOperation());
        $this->assertEquals('account_activate', $activateLog->getOperation());
        $this->assertEquals('account_deactivate', $deactivateLog->getOperation());
        $this->assertEquals(LogLevel::WARNING, $deactivateLog->getLevel());
    }

    public function testBusinessScenarioCertificateIssuance(): void
    {
        $this->entityManager->expects($this->exactly(4))
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->exactly(4))
            ->method('flush');

        // 订单创建
        $orderLog = $this->service->logOrderOperation(
            'create',
            'Order created for domain example.com',
            456,
            ['domains' => ['example.com']]
        );

        // 质询验证
        $challengeLog = $this->service->logChallengeOperation(
            'validate',
            'DNS challenge validated',
            789,
            ['type' => 'dns-01', 'domain' => 'example.com']
        );

        // 证书签发
        $issueLog = $this->service->logCertificateOperation(
            'issue',
            'Certificate issued successfully',
            101,
            ['serial' => 'ABC123DEF456', 'expires' => '2024-12-31']
        );

        // 证书下载
        $downloadLog = $this->service->logCertificateOperation(
            'download',
            'Certificate downloaded',
            101,
            ['format' => 'pem']
        );

        $this->assertEquals('Order', $orderLog->getEntityType());
        $this->assertEquals('Challenge', $challengeLog->getEntityType());
        $this->assertEquals('Certificate', $issueLog->getEntityType());
        $this->assertEquals('Certificate', $downloadLog->getEntityType());
    }

    public function testEdgeCasesEmptyStrings(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logOperation('', '');

        $this->assertEquals('', $result->getOperation());
        $this->assertEquals('', $result->getMessage());
    }

    public function testEdgeCasesLargeData(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeOperationLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $largeContext = array_fill(0, 1000, 'data');
        $longMessage = str_repeat('A', 10000);

        $result = $this->service->logOperation(
            'large_operation',
            $longMessage,
            'LargeEntity',
            999999,
            $largeContext
        );

        $this->assertEquals('large_operation', $result->getOperation());
        $this->assertEquals($longMessage, $result->getMessage());
        $this->assertEquals($largeContext, $result->getContext());
    }
}