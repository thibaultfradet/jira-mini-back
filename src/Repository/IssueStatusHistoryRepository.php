<?php

namespace App\Repository;

use App\Entity\IssueStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueStatusHistory>
 */
class IssueStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueStatusHistory::class);
    }

    /**
     * Returns the date of the last transition to 'done' for each issue.
     * Keyed by issue id, value is DateTimeImmutable or null.
     *
     * @param int[] $issueIds
     * @return array<int, \DateTimeImmutable>
     */
    public function findLastDoneTransitionByIssueIds(array $issueIds): array
    {
        if (empty($issueIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('h')
            ->select('IDENTITY(h.issue) as issueId, MAX(h.changedAt) as lastDoneAt')
            ->where('h.issue IN (:ids)')
            ->andWhere('h.toStatus = :done')
            ->setParameter('ids', $issueIds)
            ->setParameter('done', 'done')
            ->groupBy('h.issue')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['issueId']] = new \DateTimeImmutable($row['lastDoneAt']);
        }

        return $result;
    }
}
