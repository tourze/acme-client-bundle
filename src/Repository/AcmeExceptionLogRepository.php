<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\ACMEClientBundle\Entity\AcmeExceptionLog;

/**
 * @method AcmeExceptionLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AcmeExceptionLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AcmeExceptionLog[] findAll()
 * @method AcmeExceptionLog[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AcmeExceptionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AcmeExceptionLog::class);
    }
}
