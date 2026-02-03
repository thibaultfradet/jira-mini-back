<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return array[] Top 5 projects with opened/finished issue counts
     */
    public function findTopActive(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->select(
                'p',
                'SUM(CASE WHEN i.status IN (:opened) THEN 1 ELSE 0 END) AS openedIssueCount',
                'SUM(CASE WHEN i.status = :done THEN 1 ELSE 0 END) AS finishedIssueCount'
            )
            ->leftJoin('p.issues', 'i')
            ->setParameter('opened', ['todo', 'in_progress'])
            ->setParameter('done', 'done')
            ->groupBy('p.id')
            ->orderBy('openedIssueCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
