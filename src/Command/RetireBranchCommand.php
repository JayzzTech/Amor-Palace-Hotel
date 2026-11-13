<?php

namespace App\Command;

use App\Entity\Branch;
use App\Entity\Reservation;
use App\Entity\Room;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:branch:retire', description: 'Retire a hotel branch without deleting booking history')]
class RetireBranchCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Exact name of the hotel branch to retire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = trim((string) $input->getArgument('name'));
        $branch = $this->em->getRepository(Branch::class)->findOneBy(['name' => $name]);
        if (!$branch) {
            $output->writeln(sprintf('<error>Hotel "%s" was not found.</error>', $name));
            return Command::FAILURE;
        }

        $activeReservations = (int) $this->em->createQueryBuilder()
            ->select('COUNT(reservation.id)')
            ->from(Reservation::class, 'reservation')
            ->join('reservation.room', 'room')
            ->where('room.branch = :branch')
            ->andWhere('reservation.status IN (:statuses)')
            ->setParameter('branch', $branch)
            ->setParameter('statuses', Reservation::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeReservations > 0) {
            $output->writeln(sprintf('<error>Cannot retire "%s": it has %d active reservation(s).</error>', $name, $activeReservations));
            return Command::FAILURE;
        }

        $branch->setActive(false);
        foreach ($this->em->getRepository(Room::class)->findBy(['branch' => $branch]) as $room) {
            $room->setActive(false);
        }
        $this->em->flush();

        $output->writeln(sprintf('<info>Retired "%s". Booking history and assigned accounts were preserved.</info>', $name));
        return Command::SUCCESS;
    }
}
