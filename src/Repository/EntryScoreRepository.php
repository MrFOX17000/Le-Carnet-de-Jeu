<?php

namespace App\Repository;

use App\Entity\EntryScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntryScore>
 */
class EntryScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntryScore::class);
    }

    /**
     * @param int[] $groupIds
     * @return array<int, array{name:string, count:int}>
     */
    public function findTopParticipantsForActivityInGroups(int $activityId, array $groupIds, int $limit = 3): array
    {
        if ([] === $groupIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('es')
            ->select('es.participantName AS participantName')
            ->addSelect('COUNT(es.id) AS participationCount')
            ->innerJoin('es.entry', 'e')
            ->innerJoin('e.session', 's')
            ->andWhere('s.activity = :activityId')
            ->andWhere('s.group IN (:groupIds)')
            ->setParameter('activityId', $activityId)
            ->setParameter('groupIds', $groupIds)
            ->groupBy('es.participantName')
            ->orderBy('participationCount', 'DESC')
            ->addOrderBy('es.participantName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): array => [
            'name' => (string) $row['participantName'],
            'count' => (int) $row['participationCount'],
        ], $rows);
    }

    public function sumScoreForEntriesCreatedByUserInGroup(int $userId, int $groupId): float
    {
        $result = $this->createQueryBuilder('es')
            ->select('COALESCE(SUM(es.score), 0) AS totalScore')
            ->innerJoin('es.entry', 'e')
            ->andWhere('e.createdBy = :userId')
            ->andWhere('e.group = :groupId')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (float) $result;
    }
}
