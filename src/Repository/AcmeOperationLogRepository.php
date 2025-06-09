<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\ACMEClientBundle\Entity\AcmeOperationLog;

/**
 * @method AcmeOperationLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AcmeOperationLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AcmeOperationLog[] findAll()
 * @method AcmeOperationLog[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AcmeOperationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AcmeOperationLog::class);
    }
}
