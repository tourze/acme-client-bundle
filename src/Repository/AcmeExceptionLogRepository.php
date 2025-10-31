<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<AcmeExceptionLog>
 */
#[AsRepository(entityClass: AcmeExceptionLog::class)]
class AcmeExceptionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AcmeExceptionLog::class);
    }

    public function save(AcmeExceptionLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AcmeExceptionLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
