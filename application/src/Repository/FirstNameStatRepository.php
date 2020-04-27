<?php

namespace App\Repository;

use App\Entity\FirstNameStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FirstNameStat|null find($id, $lockMode = null, $lockVersion = null)
 * @method FirstNameStat|null findOneBy(array $criteria, array $orderBy = null)
 * @method FirstNameStat[]    findAll()
 * @method FirstNameStat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FirstNameStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FirstNameStat::class);
    }
}
