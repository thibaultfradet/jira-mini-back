<?php

namespace App\Repository;

use App\Entity\Sprint;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sprint>
 */
class SprintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sprint::class);
    }

    public function findActiveByTeam(Team $team): ?Sprint
    {
        return $this->findOneBy(['team' => $team, 'status' => Sprint::STATUS_ACTIVE]);
    }

    public function findNextPlannedByTeam(Team $team): ?Sprint
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->andWhere('s.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', Sprint::STATUS_PLANNED)
            ->orderBy('s.startDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Sprint[]
     */
    public function findByTeamOrderedByDate(Team $team): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->setParameter('team', $team)
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the last $limit completed or active sprints for a team, most recent first.
     *
     * @return Sprint[]
     */
    public function findLastCompletedOrActiveByTeam(Team $team, int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :team')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('team', $team)
            ->setParameter('statuses', [Sprint::STATUS_ACTIVE, Sprint::STATUS_COMPLETED])
            ->orderBy('s.endDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
