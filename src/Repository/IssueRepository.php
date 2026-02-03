<?php

namespace App\Repository;

use App\Entity\Issue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Issue>
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    /**
     * @return Issue[]
     */
    public function findBacklog(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.sprints', 's')
            ->where('s.id IS NULL OR i.status != :done')
            ->andWhere('i.type != :epic')
            ->setParameter('done', 'done')
            ->setParameter('epic', 'epic')
            ->groupBy('i.id')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
