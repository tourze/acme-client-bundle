<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\ACMEClientBundle\Repository\AcmeExceptionLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<AcmeExceptionLog>
 * @internal
 */
#[CoversClass(AcmeExceptionLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class AcmeExceptionLogRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类自定义 setUp 逻辑
    }

    public function testFindAllReturnsArray(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $results = $repository->findAll();
        // Verify we have some results from fixtures
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        }
    }

    public function testFindOneByWithNullFieldShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('NullFieldTest');
        $log->setMessage('Null field test message');
        $log->setCode(0);
        $log->setStackTrace(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['stackTrace' => null]);
        $this->assertInstanceOf(AcmeExceptionLog::class, $foundLog);
        $this->assertNull($foundLog->getStackTrace());
    }

    public function testFindByWithNullFieldShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('NullEntityTypeTest');
        $log->setMessage('Null entity type test');
        $log->setCode(0);
        $log->setEntityType(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['entityType' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
            $this->assertNull($result->getEntityType());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null entityType should be found');
    }

    public function testSaveAndRemove(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('SaveRemoveTest');
        $log->setMessage('Save and remove test');
        $log->setCode(0);

        $repository->save($log);
        $logId = $log->getId();
        $this->assertNotNull($logId);

        $repository->remove($log);

        $removedLog = $repository->find($logId);
        $this->assertNull($removedLog);
    }

    public function testFindOneByWithOrderByShouldReturnEntityInCorrectOrder(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        // Create multiple logs with different exception classes for sorting
        $log1 = new AcmeExceptionLog();
        $log1->setExceptionClass('AException');
        $log1->setMessage('Exception A');
        $log1->setCode(0);
        $repository->save($log1);

        $log2 = new AcmeExceptionLog();
        $log2->setExceptionClass('ZException');
        $log2->setMessage('Exception Z');
        $log2->setCode(0);
        $repository->save($log2);

        // Test ascending order
        $result = $repository->findOneBy(['code' => 0], ['exceptionClass' => 'ASC']);
        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $this->assertSame($log1->getId(), $result->getId());

        // Test descending order
        $result = $repository->findOneBy(['code' => 0], ['exceptionClass' => 'DESC']);
        $this->assertInstanceOf(AcmeExceptionLog::class, $result);
        $this->assertSame($log2->getId(), $result->getId());
    }

    public function testRemoveMethodDeletesEntity(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('RemoveTest');
        $log->setMessage('Remove test');
        $log->setCode(0);

        $repository->save($log);
        $logId = $log->getId();
        $this->assertNotNull($logId);

        // Verify entity exists
        $foundEntity = $repository->find($logId);
        $this->assertInstanceOf(AcmeExceptionLog::class, $foundEntity);

        // Remove entity
        $repository->remove($log);

        // Verify entity no longer exists
        $removedEntity = $repository->find($logId);
        $this->assertNull($removedEntity);
    }

    public function testFindByWithNullStackTraceShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null stack trace');
        $log->setCode(404);
        $log->setStackTrace(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['stackTrace' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
            $this->assertNull($result->getStackTrace());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null stackTrace should be found');
    }

    public function testFindOneByWithNullFileShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null file');
        $log->setCode(500);
        $log->setFile(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['file' => null]);
        $this->assertInstanceOf(AcmeExceptionLog::class, $foundLog);
        $this->assertNull($foundLog->getFile());
    }

    public function testFindByWithNullLineShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null line');
        $log->setCode(403);
        $log->setLine(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['line' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
            $this->assertNull($result->getLine());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null line should be found');
    }

    public function testFindOneByWithNullEntityTypeShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null entity type');
        $log->setCode(400);
        $log->setEntityType(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['entityType' => null]);
        $this->assertInstanceOf(AcmeExceptionLog::class, $foundLog);
        $this->assertNull($foundLog->getEntityType());
    }

    public function testFindByWithNullEntityIdShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null entity ID');
        $log->setCode(401);
        $log->setEntityId(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['entityId' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
            $this->assertNull($result->getEntityId());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null entityId should be found');
    }

    public function testFindOneByWithNullContextShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null context');
        $log->setCode(402);
        $log->setContext(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['context' => null]);
        $this->assertInstanceOf(AcmeExceptionLog::class, $foundLog);
        $this->assertNull($foundLog->getContext());
    }

    public function testFindByWithNullHttpUrlShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null HTTP URL');
        $log->setCode(502);
        $log->setHttpUrl(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['httpUrl' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
            $this->assertNull($result->getHttpUrl());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null httpUrl should be found');
    }

    public function testFindOneByWithNullHttpMethodShouldReturnEntity(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null HTTP method');
        $log->setCode(503);
        $log->setHttpMethod(null); // Nullable field

        $repository->save($log);

        $foundLog = $repository->findOneBy(['httpMethod' => null]);
        $this->assertInstanceOf(AcmeExceptionLog::class, $foundLog);
        $this->assertNull($foundLog->getHttpMethod());
    }

    public function testFindByWithNullHttpStatusCodeShouldReturnEntities(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Test exception with null HTTP status code');
        $log->setCode(504);
        $log->setHttpStatusCode(null); // Nullable field

        $repository->save($log);

        $results = $repository->findBy(['httpStatusCode' => null]);
        // Type is guaranteed by repository method signature
        $this->assertGreaterThanOrEqual(1, count($results));

        $found = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(AcmeExceptionLog::class, $result);
            $this->assertNull($result->getHttpStatusCode());
            if ($result->getId() === $log->getId()) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Log with null httpStatusCode should be found');
    }

    public function testCountWithNullStackTraceShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null stack trace');
        $log->setCode(600);
        $log->setStackTrace(null);
        $repository->save($log);

        $count = $repository->count(['stackTrace' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullFileShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null file');
        $log->setCode(601);
        $log->setFile(null);
        $repository->save($log);

        $count = $repository->count(['file' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullLineShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null line');
        $log->setCode(602);
        $log->setLine(null);
        $repository->save($log);

        $count = $repository->count(['line' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullEntityTypeShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null entity type');
        $log->setCode(603);
        $log->setEntityType(null);
        $repository->save($log);

        $count = $repository->count(['entityType' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullEntityIdShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null entity ID');
        $log->setCode(604);
        $log->setEntityId(null);
        $repository->save($log);

        $count = $repository->count(['entityId' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullContextShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null context');
        $log->setCode(605);
        $log->setContext(null);
        $repository->save($log);

        $count = $repository->count(['context' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullHttpUrlShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null HTTP URL');
        $log->setCode(606);
        $log->setHttpUrl(null);
        $repository->save($log);

        $count = $repository->count(['httpUrl' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullHttpMethodShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null HTTP method');
        $log->setCode(607);
        $log->setHttpMethod(null);
        $repository->save($log);

        $count = $repository->count(['httpMethod' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullHttpStatusCodeShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(AcmeExceptionLogRepository::class);

        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException');
        $log->setMessage('Count test with null HTTP status code');
        $log->setCode(608);
        $log->setHttpStatusCode(null);
        $repository->save($log);

        $count = $repository->count(['httpStatusCode' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * @return ServiceEntityRepository<AcmeExceptionLog>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(AcmeExceptionLogRepository::class);
    }

    protected function createNewEntity(): AcmeExceptionLog
    {
        $log = new AcmeExceptionLog();
        $log->setExceptionClass('TestException_' . uniqid());
        $log->setMessage('Test exception message ' . uniqid());
        $log->setCode(rand(100, 599));
        $log->setStackTrace(null);
        $log->setFile(null);
        $log->setLine(null);
        $log->setEntityType(null);
        $log->setEntityId(null);
        $log->setContext(null);
        $log->setHttpUrl(null);
        $log->setHttpMethod(null);
        $log->setHttpStatusCode(null);
        $log->setResolved(false);
        $log->setOccurredTime(new \DateTimeImmutable());

        return $log;
    }
}
