<?php

namespace App\Repository;

use App\Entity\Invite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invite>
 */
class InviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invite::class);
    }

    /**
     * @return Invite[] Returns pending (non-accepted, non-expired) invites for the given email
     */
    public function findPendingInvites(string $email): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.email = :email')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
