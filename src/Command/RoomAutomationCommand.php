<?php

namespace App\Command;

use App\Entity\Reservation;
use App\Entity\Room;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:rooms:automate', description: 'Apply reservation and room housekeeping automation')]
class RoomAutomationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoomRepository $rooms,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $expiredPending = new \DateTimeImmutable('-24 hours');
        $checkedOut = 0;
        $cancelled = 0;

        foreach ($this->em->getRepository(Reservation::class)->findAll() as $reservation) {
            if ($reservation->getStatus() === 'checked_in' && $reservation->getCheckOut() <= $now) {
                $reservation->setStatus('checked_out')
                    ->setActualCheckOut($reservation->getActualCheckOut() ?: $now);
                $room = $reservation->getRoom();
                $room->setHousekeepingStatus('dirty');
                $this->rooms->synchronizeAvailability($room);
                ++$checkedOut;
            } elseif ($reservation->getStatus() === 'confirmed' && $reservation->getCheckOut() <= $now) {
                $reservation->setStatus('cancelled');
                ++$cancelled;
            } elseif ($reservation->getStatus() === 'pending' && $reservation->getCreatedAt() <= $expiredPending) {
                $reservation->setStatus('cancelled');
                ++$cancelled;
            }
        }

        foreach ($this->em->getRepository(Room::class)->findAll() as $room) {
            $this->rooms->synchronizeAvailability($room);
        }

        $this->em->flush();
        $output->writeln(sprintf('Automation complete: %d stays checked out, %d reservations cancelled.', $checkedOut, $cancelled));

        return Command::SUCCESS;
    }
}
