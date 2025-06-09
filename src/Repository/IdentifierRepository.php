<?php

declare(strict_types=1);

namespace Tourze\ACMEClientBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\ACMEClientBundle\Entity\Identifier;

/**
 * @method Identifier|null find($id, $lockMode = null, $lockVersion = null)
 * @method Identifier|null findOneBy(array $criteria, array $orderBy = null)
 * @method Identifier[] findAll()
 * @method Identifier[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdentifierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Identifier::class);
    }
}
