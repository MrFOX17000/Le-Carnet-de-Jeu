<?php

namespace App\Repository;

use App\Entity\Entry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entry>
 */
class EntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entry::class);
    }

    public function countDistinctSessionsCreatedByUserInGroup(int $userId, int $groupId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT s.id)')
            ->innerJoin('e.session', 's')
            ->andWhere('e.createdBy = :userId')
            ->andWhere('e.group = :groupId')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countEntriesCreatedByUserInGroup(int $userId, int $groupId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.createdBy = :userId')
            ->andWhere('e.group = :groupId')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countMatchEntriesCreatedByUserInGroup(int $userId, int $groupId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.createdBy = :userId')
            ->andWhere('e.group = :groupId')
            ->andWhere('e.type = :matchType')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->setParameter('matchType', 'match')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * @return array{wins:int, losses:int}
     */
    public function countWinLossForUserInGroup(int $userId, int $groupId): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('m.homeScore AS homeScore')
            ->addSelect('m.awayScore AS awayScore')
            ->innerJoin('e.entryMatch', 'm')
            ->andWhere('e.createdBy = :userId')
            ->andWhere('e.group = :groupId')
            ->andWhere('e.type = :matchType')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->setParameter('matchType', 'match')
            ->getQuery()
            ->getArrayResult()
        ;

        $wins = 0;
        $losses = 0;

        foreach ($rows as $row) {
            $homeScore = (int) $row['homeScore'];
            $awayScore = (int) $row['awayScore'];

            if ($homeScore > $awayScore) {
                ++$wins;
            } elseif ($homeScore < $awayScore) {
                ++$losses;
            }
        }

        return [
            'wins' => $wins,
            'losses' => $losses,
        ];
    }
}
