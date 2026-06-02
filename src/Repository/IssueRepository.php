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
    public function findAssignedTo(int $userId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.assignee = :userId')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.type != :epic')
            ->setParameter('userId', $userId)
            ->setParameter('statuses', ['in_progress', 'todo'])
            ->setParameter('epic', 'epic')
            ->orderBy('i.status', 'ASC')
            ->addOrderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Issues not in any active sprint and not done.
     *
     * @return Issue[]
     */
    public function findBacklog(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.sprints', 's')
            ->where('i.type != :epic')
            ->andWhere('i.status != :done')
            ->andWhere('s.id IS NULL OR s.status != :active')
            ->setParameter('epic', 'epic')
            ->setParameter('done', 'done')
            ->setParameter('active', 'active')
            ->groupBy('i.id')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
