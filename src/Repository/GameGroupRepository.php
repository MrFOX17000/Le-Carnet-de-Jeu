<?php

namespace App\Repository;

use App\Entity\GameGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameGroup>
 */
class GameGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameGroup::class);
    }

    /**
     * @return GameGroup[] Returns all groups where the given user is a member
     */
    public function findGroupsForUser(int $userId): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.groupMembers', 'm')
            ->addSelect('m')
            ->andWhere('m.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
