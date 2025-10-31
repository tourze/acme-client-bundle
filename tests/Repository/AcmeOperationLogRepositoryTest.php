<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;
use Tourze\ACMEClientBundle\Enum\LogLevel;
use Tourze\ACMEClientBundle\Repository\AcmeOperationLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(AcmeOperationLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class AcmeOperationLogRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $results = $repository->findAll();
        // Type is guaranteed by repository method signature
        $this->assertIsArray($results);
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeOperationLog::class, $result);
        }
    }

    public function testFindOneByWithNullFieldShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_field_test');
        $log->setMessage('Null field test message');
        $log->setEntityType(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['entityType' => null]);
        $this->assertInstanceOf(AcmeOperationLog::class, $foundLog);
        $this->assertNull($foundLog->getEntityType());
    }

    public function testFindByWithNullFieldShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_entity_id_test');
        $log->setMessage('Null entity ID test');
        $log->setEntityId(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['entityId' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeOperationLog::class, $result);
            $this->assertNull($result->getEntityId());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null entityId should be found');
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('save_remove_test');
        $log->setMessage('Save and remove test');

        $repository->save($log);
        $logId = $log->getId();
        $this->assertNotNull($logId);

        $repository->remove($log);

        $removedLog = $repository->find($logId);
        $this->assertNull($removedLog);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);

        // Create multiple logs with different operations for sorting
        $log1 = new AcmeOperationLog();
        $log1->setLevel(LogLevel::INFO);
        $log1->setOperation('a_operation');
        $log1->setMessage('Operation A');
        $repository->save($log1);

        $log2 = new AcmeOperationLog();
        $log2->setLevel(LogLevel::INFO);
        $log2->setOperation('z_operation');
        $log2->setMessage('Operation Z');
        $repository->save($log2);

        // Test ascending order
        $result = $repository->findOneBy(['level' => LogLevel::INFO], ['operation' => 'ASC']);
        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertSame($log1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['level' => LogLevel::INFO], ['operation' => 'DESC']);
        $this->assertInstanceOf(AcmeOperationLog::class, $result);
        $this->assertSame($log2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('remove_test');
        $log->setMessage('Remove test');

        $repository->save($log);
        $logId = $log->getId();
        $this->assertNotNull($logId);

        // Verify entity exists
        $foundEntity = $repository->find($logId);
        $this->assertInstanceOf(AcmeOperationLog::class, $foundEntity);

        // Remove entity
        $repository->remove($log);

        // Verify entity no longer exists
        $removedEntity = $repository->find($logId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithNullContextShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_context_test');
        $log->setMessage('Null context test');
        $log->setContext(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['context' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeOperationLog::class, $result);
            $this->assertNull($result->getContext());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null context should be found');
    }

    public function testFindOneByWithNullHttpUrlShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_http_url_test');
        $log->setMessage('Null HTTP URL test');
        $log->setHttpUrl(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['httpUrl' => null]);
        $this->assertInstanceOf(AcmeOperationLog::class, $foundLog);
        $this->assertNull($foundLog->getHttpUrl());
    }

    public function testFindByWithNullHttpMethodShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_http_method_test');
        $log->setMessage('Null HTTP method test');
        $log->setHttpMethod(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['httpMethod' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeOperationLog::class, $result);
            $this->assertNull($result->getHttpMethod());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null httpMethod should be found');
    }

    public function testFindOneByWithNullHttpStatusCodeShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_http_status_test');
        $log->setMessage('Null HTTP status code test');
        $log->setHttpStatusCode(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['httpStatusCode' => null]);
        $this->assertInstanceOf(AcmeOperationLog::class, $foundLog);
        $this->assertNull($foundLog->getHttpStatusCode());
    }

    public function testFindByWithNullDurationMsShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('null_duration_test');
        $log->setMessage('Null duration test');
        $log->setDurationMs(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['durationMs' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeOperationLog::class, $result);
            $this->assertNull($result->getDurationMs());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null durationMs should be found');
    }

    public function testCountWithNullContextShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);

        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('count_null_context_test');
        $log->setMessage('Count null context test');
        $log->setContext(null);
        $repository->save($log);

        $count = $repository->count(['context' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullHttpUrlShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);

        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('count_null_http_url_test');
        $log->setMessage('Count null HTTP URL test');
        $log->setHttpUrl(null);
        $repository->save($log);

        $count = $repository->count(['httpUrl' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullHttpMethodShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);

        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('count_null_http_method_test');
        $log->setMessage('Count null HTTP method test');
        $log->setHttpMethod(null);
        $repository->save($log);

        $count = $repository->count(['httpMethod' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullHttpStatusCodeShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);

        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('count_null_http_status_test');
        $log->setMessage('Count null HTTP status test');
        $log->setHttpStatusCode(null);
        $repository->save($log);

        $count = $repository->count(['httpStatusCode' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullDurationMsShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeOperationLogRepository::class);

        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('count_null_duration_test');
        $log->setMessage('Count null duration test');
        $log->setDurationMs(null);
        $repository->save($log);

        $count = $repository->count(['durationMs' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * @return ServiceEntityRepository<AcmeOperationLog>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(AcmeOperationLogRepository::class);
    }

    protected function createNewEntity(): object
    {
        $log = new AcmeOperationLog();
        $log->setLevel(LogLevel::INFO);
        $log->setOperation('test_operation_' . uniqid());
        $log->setMessage('Test operation message');

        return $log;
    }
}
