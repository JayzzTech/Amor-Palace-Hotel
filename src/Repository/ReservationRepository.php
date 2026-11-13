<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Reservation> */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Reservation::class); }

    public function forBranch(?Branch $branch, ?string $status = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.room', 'rm')->addSelect('rm')->join('r.guest', 'g')->addSelect('g')
            ->orderBy('r.createdAt', 'DESC')->setMaxResults($limit);
        if ($branch) $qb->andWhere('rm.branch = :b')->setParameter('b', $branch);
        if ($status) $qb->andWhere('r.status = :s')->setParameter('s', $status);

        return $qb->getQuery()->getResult();
    }

    public function countFor(?Branch $branch, array $statuses, ?\DateTimeImmutable $checkIn = null): int
    {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)')->join('r.room', 'rm')
            ->where('r.status IN (:s)')->setParameter('s', $statuses);
        if ($branch) $qb->andWhere('rm.branch = :b')->setParameter('b', $branch);
        if ($checkIn) {
            $nextDay = $checkIn->modify('+1 day');
            $qb->andWhere('r.checkIn >= :d')->andWhere('r.checkIn < :next')
                ->setParameter('d', $checkIn, Types::DATETIME_IMMUTABLE)
                ->setParameter('next', $nextDay, Types::DATETIME_IMMUTABLE);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Revenue from confirmed, in-house and completed stays. */
    public function revenueFor(?Branch $branch): int
    {
        $qb = $this->createQueryBuilder('r')->select('COALESCE(SUM(r.total), 0)')->join('r.room', 'rm')
            ->where('r.status IN (:s)')->setParameter('s', ['confirmed', 'checked_in', 'checked_out']);
        if ($branch) $qb->andWhere('rm.branch = :b')->setParameter('b', $branch);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
