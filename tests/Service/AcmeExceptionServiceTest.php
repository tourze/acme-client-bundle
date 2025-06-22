<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Repository\AcmeExceptionLogRepository;
use Tourze\ACMEClientBundle\Service\AcmeExceptionService;

/**
 * AcmeExceptionService 测试
 */
class AcmeExceptionServiceTest extends TestCase
{
    private AcmeExceptionService $service;
    
    /** @var EntityManagerInterface */
    private $entityManager;
    
    /** @var AcmeExceptionLogRepository */
    private $repository;
    
    /** @var QueryBuilder */
    private $queryBuilder;
    
    /** @var Query */
    private $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(AcmeExceptionLogRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);
        
        $this->service = new AcmeExceptionService($this->entityManager, $this->repository);
    }

    public function testConstructor(): void
    {
        $service = new AcmeExceptionService($this->entityManager, $this->repository);
        $this->assertInstanceOf(AcmeExceptionService::class, $service);
    }

    public function testLogException(): void
    {
        $exception = new \RuntimeException('Test exception', 500);
        
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeExceptionLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logException(
            $exception,
            'TestEntity',
            123,
            ['context' => 'test']
        );

        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $this->assertEquals('RuntimeException', $result->getExceptionClass());
        $this->assertEquals('Test exception', $result->getMessage());
        $this->assertEquals(500, $result->getCode());
        $this->assertEquals('TestEntity', $result->getEntityType());
        $this->assertEquals(123, $result->getEntityId());
    }

    public function testLogExceptionWithDefaults(): void
    {
        $exception = new \Exception('Simple exception');
        
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeExceptionLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->logException($exception);

        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $this->assertEquals('Exception', $result->getExceptionClass());
        $this->assertEquals('Simple exception', $result->getMessage());
        $this->assertEquals(0, $result->getCode());
        $this->assertNull($result->getEntityType());
        $this->assertNull($result->getEntityId());
    }

    public function testFindExceptionsWithAllFilters(): void
    {
        $expectedExceptions = [
            new AcmeExceptionLog(),
            new AcmeExceptionLog(),
        ];
        $since = new \DateTimeImmutable('-1 hour');

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('e.occurredAt', 'DESC')
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
            ->willReturn($expectedExceptions);

        $result = $this->service->findExceptions(
            'RuntimeException',
            'TestEntity',
            123,
            $since,
            50
        );

        $this->assertEquals($expectedExceptions, $result);
    }

    public function testFindExceptionsWithDefaults(): void
    {
        $expectedExceptions = [];

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('e.occurredAt', 'DESC')
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
            ->willReturn($expectedExceptions);

        $result = $this->service->findExceptions();

        $this->assertEquals($expectedExceptions, $result);
    }

    public function testFindExceptionsWithPartialFilters(): void
    {
        $expectedExceptions = [new AcmeExceptionLog()];

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('e.occurredAt', 'DESC')
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
            ->willReturn($expectedExceptions);

        $result = $this->service->findExceptions(
            'RuntimeException',
            'TestEntity'
        );

        $this->assertEquals($expectedExceptions, $result);
    }

    public function testGetExceptionStats(): void
    {
        $expectedStats = [
            ['exceptionClass' => 'RuntimeException', 'count' => 5],
            ['exceptionClass' => 'InvalidArgumentException', 'count' => 3],
        ];

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('e.exceptionClass, COUNT(e.id) as count')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('groupBy')
            ->with('e.exceptionClass')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('count', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->never())
            ->method('andWhere');

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedStats);

        $result = $this->service->getExceptionStats();

        $this->assertEquals($expectedStats, $result);
    }

    public function testGetExceptionStatsWithSince(): void
    {
        $expectedStats = [
            ['exceptionClass' => 'RuntimeException', 'count' => 2],
        ];
        $since = new \DateTimeImmutable('-1 day');

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('e.exceptionClass, COUNT(e.id) as count')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('groupBy')
            ->with('e.exceptionClass')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('count', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('e.occurredAt >= :since')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('since', $since)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedStats);

        $result = $this->service->getExceptionStats($since);

        $this->assertEquals($expectedStats, $result);
    }

    public function testCleanupOldExceptions(): void
    {
        $deletedCount = 10;

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('delete')
            ->with(AcmeExceptionLog::class, 'e')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.occurredAt < :cutoffDate')
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

        $result = $this->service->cleanupOldExceptions(30);

        $this->assertEquals($deletedCount, $result);
    }

    public function testCleanupOldExceptionsWithCustomDays(): void
    {
        $deletedCount = 5;

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('delete')
            ->with(AcmeExceptionLog::class, 'e')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.occurredAt < :cutoffDate')
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

        $result = $this->service->cleanupOldExceptions(7);

        $this->assertEquals($deletedCount, $result);
    }

    public function testHasDuplicateExceptionTrue(): void
    {
        $mockException = new AcmeExceptionLog();

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->exactly(4))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(5))
            ->method('setParameter')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.exceptionClass = :exceptionClass')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($mockException);

        $result = $this->service->hasDuplicateException(
            'RuntimeException',
            'Test message',
            'TestEntity',
            123,
            5
        );

        $this->assertTrue($result);
    }

    public function testHasDuplicateExceptionFalse(): void
    {
        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.exceptionClass = :exceptionClass')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $result = $this->service->hasDuplicateException(
            'RuntimeException',
            'Test message'
        );

        $this->assertFalse($result);
    }

    public function testHasDuplicateExceptionWithEntityInfo(): void
    {
        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->exactly(4))
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(5))
            ->method('setParameter')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.exceptionClass = :exceptionClass')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $result = $this->service->hasDuplicateException(
            'RuntimeException',
            'Test message',
            'TestEntity',
            123,
            10
        );

        $this->assertFalse($result);
    }

    public function testGetRecentExceptions(): void
    {
        $expectedExceptions = [
            new AcmeExceptionLog(),
            new AcmeExceptionLog(),
        ];

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.occurredAt >= :since')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('e.occurredAt', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(50)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('since', $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedExceptions);

        $result = $this->service->getRecentExceptions(24, 50);

        $this->assertEquals($expectedExceptions, $result);
    }

    public function testGetRecentExceptionsWithDefaults(): void
    {
        $expectedExceptions = [];

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('e.occurredAt >= :since')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('e.occurredAt', 'DESC')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(50)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('since', $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedExceptions);

        $result = $this->service->getRecentExceptions();

        $this->assertEquals($expectedExceptions, $result);
    }

    public function testBusinessScenarioExceptionTracking(): void
    {
        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(AcmeExceptionLog::class));
        
        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 网络异常
        $networkException = new \RuntimeException('Connection timeout', 500);
        $networkLog = $this->service->logException(
            $networkException,
            'Account',
            123,
            ['url' => 'https://acme-v02.api.letsencrypt.org/acme/new-account']
        );

        // 验证异常
        $validationException = new \InvalidArgumentException('Invalid domain format', 400);
        $validationLog = $this->service->logException(
            $validationException,
            'Order',
            456,
            ['domain' => 'invalid..domain']
        );

        // 证书异常
        $certException = new \Exception('Certificate parsing failed');
        $certLog = $this->service->logException(
            $certException,
            'Certificate',
            789
        );

        $this->assertEquals('RuntimeException', $networkLog->getExceptionClass());
        $this->assertEquals('InvalidArgumentException', $validationLog->getExceptionClass());
        $this->assertEquals('Exception', $certLog->getExceptionClass());
    }

    public function testEdgeCasesExceptionTypes(): void
    {
        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(AcmeExceptionLog::class));
        
        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // 自定义异常
        $customException = new class('Custom error') extends \Exception {};
        $customLog = $this->service->logException($customException);

        // 错误对象
        $error = new \Error('Fatal error');
        $errorLog = $this->service->logException($error);

        // 带有前一个异常的异常
        $previous = new \RuntimeException('Previous error');
        $chainedException = new \Exception('Chained error', 0, $previous);
        $chainedLog = $this->service->logException($chainedException);

        $this->assertStringContainsString('Exception@anonymous', $customLog->getExceptionClass());
        $this->assertEquals('Error', $errorLog->getExceptionClass());
        $this->assertEquals('Exception', $chainedLog->getExceptionClass());
    }

    public function testEdgeCasesLargeData(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AcmeExceptionLog::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $longMessage = str_repeat('A', 10000);
        $largeContext = array_fill(0, 1000, 'data');
        
        $exception = new \RuntimeException($longMessage, 999);
        $result = $this->service->logException(
            $exception,
            'LargeEntity',
            999999,
            $largeContext
        );

        $this->assertEquals($longMessage, $result->getMessage());
        $this->assertEquals(999, $result->getCode());
        $this->assertEquals($largeContext, $result->getContext());
    }
}