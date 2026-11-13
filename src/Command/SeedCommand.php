<?php

namespace App\Command;

use App\Entity\Branch;
use App\Entity\Reservation;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Load demo branches, rooms, users and reservations')]
class SeedCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->em->getRepository(Branch::class)->count([]) > 0) {
            $this->updateDemoEmailDomain();
            $this->ensureBranchAdmins();
            foreach ($this->em->getRepository(Branch::class)->findAll() as $branch) {
                match ($branch->getId()) {
                    1 => $branch->setName('Amor Loco Hotel'),
                    2 => $branch->setName('Mi Amor Hotel'),
                    default => null,
                };
                if ($branch->getName() === 'Lynmore Hotel') $branch->setActive(false);
            }
            foreach ($this->em->getRepository(Room::class)->findAll() as $room) {
                if ($room->getBranch()->getId() === 1) {
                    if (str_contains(strtolower($room->getType()), 'standard')) {
                        $room->setPricePerNight(1950);
                    } else {
                        $room->setPricePerNight(1850);
                    }
                } elseif ($room->getBranch()->getId() === 2) {
                    $room->setPricePerNight(str_contains(strtolower($room->getType()), 'tryke') ? 1700 : 2000);
                    if (!str_contains(strtolower($room->getType()), 'tryke')) {
                        $room->setType('Executive Room');
                    }
                }
                if (in_array($room->getBranch()->getId(), [1, 2], true)) {
                    $room->setActive(true)->setActiveBooking(false);
                }
                if ($room->getBranch()->getName() === 'Lynmore Hotel') $room->setActive(false);
            }
            $this->em->flush();
            $output->writeln('<comment>Data already exists. Seed skipped.</comment>');

            return Command::SUCCESS;
        }
        $data = [
            ['Amor Loco Hotel', 'Magalang', 'Magalang', '+63 2 8555 0101', 'Quiet luxury in a peaceful setting', 'A refined retreat with thoughtfully appointed rooms, a rooftop lounge, spa and welcoming service.', 1.0],
            ['Mi Amor Hotel', 'Pandacaqui', 'Pandacaqui', '+63 2 8555 0202', 'Warm hospitality and timeless charm', 'Relax in a welcoming hotel with warm wood, brass details and a courtyard garden.', 0.95],
        ];
        $types = [
            ['Tryke Room', 2, 1850, 'Comfortable bed, Air conditioning, Private bath, Fast Wi-Fi', 'A practical, comfortable room for short stays and easy city travel.'],
            ['Standard Room', 2, 1950, 'Queen bed, Air conditioning, Work desk, Fast Wi-Fi', 'A welcoming room with everything needed for a restful stay.'],
        ];
        $branches = [];
        foreach ($data as $i => [$name, $city, $addr, $phone, $tag, $desc, $mult]) {
            $b = (new Branch())->setName($name)->setCity($city)->setAddress($addr)->setPhone($phone)->setTagline($tag)->setDescription($desc);
            $this->em->persist($b);
            $branches[] = $b;
            foreach ($types as $j => [$type, $cap, $amorPrice, $amen, $rdesc]) {
                $price = match ($name) {
                    'Amor Loco Hotel' => $amorPrice,
                    'Mi Amor Hotel' => $type === 'Tryke Room' ? 1700 : 2000,
                    default => $amorPrice,
                };
                $seedPrice = $name === 'Amor Loco Hotel' || $name === 'Mi Amor Hotel'
                    ? $price
                    : (int) (round($price * $mult / 100) * 100);
                $this->em->persist((new Room())->setBranch($b)->setNumber((string) (($j < 3 ? 100 : 200) + $j + 1))->setType($type)
                    ->setCapacity($cap)->setPricePerNight($seedPrice)->setAmenities($amen)->setDescription($rdesc));
            }
        }
        $this->em->flush();

        $mk = function (string $email, string $name, array $roles, ?Branch $branch): User {
            $u = (new User())->setEmail($email)->setFullName($name)->setRoles($roles)->setBranch($branch);
            $u->setPassword($this->hasher->hashPassword($u, 'Password123!'));
            $this->em->persist($u);

            return $u;
        };
        $mk('super@amor.test', 'Sofia Reyes', ['ROLE_SUPER_ADMIN'], null);
        $mk('amorloco@amor.test', 'Miguel Santos', ['ROLE_ADMIN'], $branches[0]);
        $mk('miamor@amor.test', 'Isabella Cruz', ['ROLE_ADMIN'], $branches[1]);
        $mk('staff@amor.test', 'Ana Dizon', ['ROLE_STAFF'], $branches[0]);
        $guest = $mk('guest@amor.test', 'Carlos Mendoza', [], null);
        $this->em->flush();

        $rooms = $this->em->getRepository(Room::class)->findBy(['branch' => $branches[0]], ['number' => 'ASC']);
        $mkRes = function (Room $room, string $in, int $nights, string $status) use ($guest): void {
            $ci = new \DateTimeImmutable($in);
            $this->em->persist((new Reservation())->setGuest($guest)->setRoom($room)->setCheckIn($ci)->setCheckOut($ci->modify("+$nights days"))
                ->setGuests(2)->setTotal($nights * $room->getPricePerNight())->setStatus($status));
        };
        $mkRes($rooms[0], '-30 days', 3, 'checked_out');
        $mkRes($rooms[2], '+10 days', 2, 'confirmed');
        $mkRes($rooms[3], '+20 days', 4, 'pending');
        $this->em->flush();

        $output->writeln('<info>Seeded. Sign in with the demo accounts at @amor.test, password "Password123!"</info>');

        return Command::SUCCESS;
    }

    private function ensureBranchAdmins(): void
    {
        $branches = $this->em->getRepository(Branch::class)->findAll();
        $users = $this->em->getRepository(User::class)->findAll();

        foreach ($branches as $branch) {
            $email = preg_replace('/hotel$/', '', strtolower(str_replace(' ', '', $branch->getName()))) . '@amor.test';
            $admins = array_values(array_filter($users, static fn (User $user): bool =>
                $user->getBranch()?->getId() === $branch->getId() && $user->getPrimaryRole() === 'ROLE_ADMIN'
            ));
            $admin = $admins[0] ?? null;

            if (!$admin) {
                $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@amor.test']);
                if ($admin && $admin->getBranch() !== null) $admin = null;
            }
            if (!$admin) {
                $admin = (new User())->setFullName($branch->getName() . ' Admin');
                $admin->setPassword($this->hasher->hashPassword($admin, 'Password123!'));
                $this->em->persist($admin);
            }

            $admin->setEmail($email)->setRoles(['ROLE_ADMIN'])->setBranch($branch);
            foreach (array_slice($admins, 1) as $extra) $extra->setRoles(['ROLE_STAFF']);
        }
    }

    private function updateDemoEmailDomain(): void
    {
        foreach ($this->em->getRepository(User::class)->findAll() as $user) {
            if (str_ends_with($user->getEmail(), '@aurelia.test')) {
                $user->setEmail(str_replace('@aurelia.test', '@amor.test', $user->getEmail()));
            }
        }
    }
}
