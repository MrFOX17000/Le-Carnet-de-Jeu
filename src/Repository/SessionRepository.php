<?php

namespace App\Repository;

use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * @return Session[]
     */
    public function findByActivityWithEntries(int $activityId): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('a', 'e', 'scores', 'match')
            ->innerJoin('s.activity', 'a')
            ->leftJoin('s.entries', 'e')
            ->leftJoin('e.scores', 'scores')
            ->leftJoin('e.entryMatch', 'match')
            ->andWhere('a.id = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('s.playedAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param int[] $groupIds
     * @return Session[] Returns the most recent sessions from given groups
     */
    public function findRecentSessionsByGroupIds(array $groupIds, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.group IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->orderBy('s.playedAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param int[] $groupIds
     * @return Session[]
     */
    public function findByGroupIdsWithEntries(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->addSelect('g', 'a', 'e', 'scores', 'match')
            ->innerJoin('s.group', 'g')
            ->innerJoin('s.activity', 'a')
            ->leftJoin('s.entries', 'e')
            ->leftJoin('e.scores', 'scores')
            ->leftJoin('e.entryMatch', 'match')
            ->andWhere('s.group IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->orderBy('s.playedAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param int[] $groupIds
     */
    public function countSessionsThisWeekByGroupIds(array $groupIds, \DateTimeImmutable $weekStart): int
    {
        if ([] === $groupIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.group IN (:groupIds)')
            ->andWhere('s.playedAt >= :weekStart')
            ->setParameter('groupIds', $groupIds)
            ->setParameter('weekStart', $weekStart)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * @param int[] $groupIds
     * @return array{activityId:int, activityName:string, groupId:int, sessionsCount:int, lastPlayedAt:\DateTimeImmutable}|null
     */
    public function findMostPlayedActivityByGroupIds(array $groupIds): ?array
    {
        if ([] === $groupIds) {
            return null;
        }

        $row = $this->createQueryBuilder('s')
            ->select('a.id AS activityId')
            ->addSelect('a.name AS activityName')
            ->addSelect('g.id AS groupId')
            ->addSelect('COUNT(s.id) AS sessionsCount')
            ->addSelect('MAX(s.playedAt) AS lastPlayedAt')
            ->innerJoin('s.activity', 'a')
            ->innerJoin('s.group', 'g')
            ->andWhere('s.group IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->groupBy('a.id, a.name, g.id')
            ->orderBy('sessionsCount', 'DESC')
            ->addOrderBy('lastPlayedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $row) {
            return null;
        }

        return [
            'activityId' => (int) $row['activityId'],
            'activityName' => (string) $row['activityName'],
            'groupId' => (int) $row['groupId'],
            'sessionsCount' => (int) $row['sessionsCount'],
            'lastPlayedAt' => $row['lastPlayedAt'],
        ];
    }

    /**
     * @param int[] $groupIds
     * @return array{groupId:int, groupName:string, sessionsCount:int}|null
     */
    public function findMostActiveGroupByGroupIds(array $groupIds): ?array
    {
        if ([] === $groupIds) {
            return null;
        }

        $row = $this->createQueryBuilder('s')
            ->select('g.id AS groupId')
            ->addSelect('g.name AS groupName')
            ->addSelect('COUNT(s.id) AS sessionsCount')
            ->innerJoin('s.group', 'g')
            ->andWhere('s.group IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->groupBy('g.id, g.name')
            ->orderBy('sessionsCount', 'DESC')
            ->addOrderBy('g.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $row) {
            return null;
        }

        return [
            'groupId' => (int) $row['groupId'],
            'groupName' => (string) $row['groupName'],
            'sessionsCount' => (int) $row['sessionsCount'],
        ];
    }
}
