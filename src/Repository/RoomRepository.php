<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Reservation;
use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Room> */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Room::class); }

    /** Active rooms of a branch with no overlapping active reservation. */
    public function findAvailable(Branch $branch, \DateTimeImmutable $in, \DateTimeImmutable $out, int $guests): array
    {
        $qb = $this->createQueryBuilder('r');
        $busy = $this->getEntityManager()->createQueryBuilder()
            ->select('x.id')->from(Reservation::class, 'x')
            ->where('x.room = r')->andWhere('x.status IN (:active)')
            ->andWhere('x.checkIn < :out')->andWhere('x.checkOut > :in')
            ->getDQL();

        return $qb->where('r.branch = :b')->andWhere('r.active = true')
            ->andWhere('r.housekeepingStatus IN (:sellableStatuses)')->andWhere('r.capacity >= :g')
            ->andWhere($qb->expr()->not($qb->expr()->exists($busy)))
            ->setParameter('b', $branch)->setParameter('g', $guests)
            ->setParameter('active', Reservation::ACTIVE)
            ->setParameter('sellableStatuses', ['clean', 'inspection'])
            ->setParameter('in', $in, Types::DATETIME_IMMUTABLE)
            ->setParameter('out', $out, Types::DATETIME_IMMUTABLE)
            ->orderBy('r.pricePerNight', 'ASC')->getQuery()->getResult();
    }

    public function isFree(Room $room, \DateTimeImmutable $in, \DateTimeImmutable $out): bool
    {
        $n = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(x.id)')->from(Reservation::class, 'x')
            ->where('x.room = :r')->andWhere('x.status IN (:active)')
            ->andWhere('x.checkIn < :out')->andWhere('x.checkOut > :in')
            ->setParameter('r', $room)->setParameter('active', Reservation::ACTIVE)
            ->setParameter('in', $in, Types::DATETIME_IMMUTABLE)
            ->setParameter('out', $out, Types::DATETIME_IMMUTABLE)
            ->getQuery()->getSingleScalarResult();

        return 0 === (int) $n && in_array($room->getHousekeepingStatus(), ['clean', 'inspection'], true);
    }

    public function hasActiveBooking(Room $room): bool
    {
        $now = new \DateTimeImmutable();
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(x.id)')->from(Reservation::class, 'x')
            ->where('x.room = :room')->andWhere('x.status IN (:active)')
            ->andWhere('x.checkIn <= :now')->andWhere('x.checkOut > :now')
            ->setParameter('room', $room)->setParameter('active', Reservation::ACTIVE)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getQuery()->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function synchronizeAvailability(Room $room): void
    {
        $booked = $this->hasActiveBooking($room);
        $room->setActiveBooking($booked)->setActive(!$booked);
        if (!in_array($room->getHousekeepingStatus(), ['clean', 'inspection'], true)) {
            $room->setActive(false);
        }
    }

    public function isFreeForExtension(Room $room, \DateTimeImmutable $in, \DateTimeImmutable $out, Reservation $reservation): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(x.id)')->from(Reservation::class, 'x')
            ->where('x.room = :r')->andWhere('x.id != :reservation')
            ->andWhere('x.status IN (:active)')
            ->andWhere('x.checkIn < :out')->andWhere('x.checkOut > :in')
            ->setParameter('r', $room)->setParameter('reservation', $reservation->getId())
            ->setParameter('active', Reservation::ACTIVE)
            ->setParameter('in', $in, Types::DATETIME_IMMUTABLE)
            ->setParameter('out', $out, Types::DATETIME_IMMUTABLE);

        return 0 === (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array<int,int> branchId => lowest nightly rate */
    public function minPrices(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.branch) AS b', 'MIN(r.pricePerNight) AS m')
            ->where('r.active = true')->groupBy('r.branch')
            ->getQuery()->getArrayResult();

        return array_column($rows, 'm', 'b');
    }
}
