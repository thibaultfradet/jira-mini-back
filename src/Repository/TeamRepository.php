<?php

namespace App\Repository;

use App\Entity\Team;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * Returns teams where the given user is a member.
     *
     * @return Team[]
     */
    public function findByMember(UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.members', 'u')
            ->where('u = :user')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
