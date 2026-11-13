<?php

namespace App\Controller;

use App\Entity\Branch;
use App\Entity\Reservation;
use App\Entity\Room;
use App\Repository\ReservationRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
class StaffController extends AppController
{
    #[Route('', name: 'app_staff')]
    public function dashboard(Request $request, EntityManagerInterface $em, ReservationRepository $res, RoomRepository $rooms): Response
    {
        $branch = $this->isGranted('ROLE_SUPER_ADMIN')
            ? $em->getRepository(Branch::class)->find((int) $request->query->get('branch'))
            : $this->me()->getBranch();
        if (!$branch) {
            $this->addFlash('error', $this->isGranted('ROLE_SUPER_ADMIN') ? 'Choose a branch to open its desk.' : 'No branch is assigned to your account yet. Ask a super admin.');

            return $this->redirectToRoute($this->isGranted('ROLE_SUPER_ADMIN') ? 'app_super' : 'app_account');
        }
        $status = (string) $request->query->get('status');
        $status = in_array($status, Reservation::STATUSES, true) ? $status : null;
        $query = trim((string) $request->query->get('q', ''));
        $today = new \DateTimeImmutable('today');

        $branchRooms = $rooms->findBy(['branch' => $branch], ['number' => 'ASC']);
        foreach ($branchRooms as $room) {
            $wasActive = $room->isActive();
            $rooms->synchronizeAvailability($room);
            if ($wasActive !== $room->isActive()) $em->persist($room);
        }
        if ($branchRooms) $em->flush();

        $reservations = $res->forBranch($branch, $status);
        if ($query !== '') {
            $needle = mb_strtolower($query);
            $reservations = array_values(array_filter($reservations, static function (Reservation $reservation) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $reservation->getReference(),
                    $reservation->getGuest()->getFullName(),
                    $reservation->getGuest()->getEmail(),
                    $reservation->getRoom()->getNumber(),
                ]));

                return str_contains($haystack, $needle);
            }));
        }

        return $this->render('staff/dashboard.html.twig', [
            'branch' => $branch, 'status' => $status,
            'query' => $query, 'reservations' => $reservations,
            'rooms' => $branchRooms,
            'stats' => [
                'arrivals' => $res->countFor($branch, ['pending', 'confirmed'], $today),
                'inhouse' => $res->countFor($branch, ['checked_in']),
                'pending' => $res->countFor($branch, ['pending']),
                'revenue' => $res->revenueFor($branch),
            ],
        ]);
    }

    #[Route('/reservation/{id}/status', name: 'app_staff_status', methods: ['POST'])]
    public function status(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        $branch = $reservation->getRoom()->getBranch();
        $this->assertCan($branch);
        $to = (string) $request->request->get('status');
        if (in_array($to, $reservation->getNextStatuses(), true)) {
            $reservation->setStatus($to);
            $roomRepo = $em->getRepository(Room::class);
            $roomRepo->synchronizeAvailability($reservation->getRoom());
            $em->flush();
            $this->addFlash('success', sprintf('%s is now %s.', $reservation->getReference(), str_replace('_', ' ', $to)));
        } else {
            $this->addFlash('error', 'That status change is not allowed.');
        }

        return $this->redirectToRoute('app_staff', ['branch' => $branch->getId()]);
    }

    #[Route('/reservation/{id}/extend', name: 'app_staff_extend', methods: ['POST'])]
    public function extend(Reservation $reservation, Request $request, RoomRepository $rooms, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        $branch = $reservation->getRoom()->getBranch();
        $this->assertCan($branch);
        $redirect = $this->redirectToRoute('app_staff', ['branch' => $branch->getId()]);

        if (!in_array($reservation->getStatus(), ['confirmed', 'checked_in'], true)) {
            $this->addFlash('error', 'Only confirmed or checked-in stays can be extended.');
            return $redirect;
        }

        $unit = (string) $request->request->get('unit', 'hours');
        $amount = (int) $request->request->get('amount');
        if (!in_array($unit, ['hours', 'days'], true)) {
            $this->addFlash('error', 'Choose hours or days for the stay extension.');
            return $redirect;
        }
        $maximum = $unit === 'days' ? 30 : 72;
        if ($amount < 1 || $amount > $maximum) {
            $this->addFlash('error', sprintf('Enter between 1 and %d %s.', $maximum, $unit));
            return $redirect;
        }

        $extensionHours = $unit === 'days' ? $amount * 24 : $amount;
        $currentCheckout = $reservation->getCheckOut();
        $newCheckout = $currentCheckout->modify(sprintf('+%d hours', $extensionHours));

        if (!$rooms->isFreeForExtension($reservation->getRoom(), $currentCheckout, $newCheckout, $reservation)) {
            $this->addFlash('error', 'The room is already booked during that extended period.');
            return $redirect;
        }

        $hourlyRate = (int) ceil($reservation->getRoom()->getPricePerNight() / 24);
        $reservation->setExtendedHours($reservation->getExtendedHours() + $extensionHours)
            ->setCheckOut($newCheckout)
            ->setTotal($reservation->getTotal() + ($extensionHours * $hourlyRate));
        $em->flush();
        $this->addFlash('success', sprintf('%s was extended by %d %s. New checkout: %s.',
            $reservation->getReference(), $amount, $unit, $newCheckout->format('d M Y, H:i')));

        return $redirect;
    }

    #[Route('/room/new', name: 'app_staff_room_new', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function newRoom(Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        $branch = $em->getRepository(Branch::class)->find((int) $request->request->get('branch'));
        if (!$branch) throw $this->createNotFoundException();
        $this->assertCan($branch);
        $p = $request->request;
        $number = trim((string) $p->get('number'));
        $type = trim((string) $p->get('type'));
        $price = (int) $p->get('price');
        if (!in_array($type, ['Tryke Room', 'Standard Room'], true)) {
            $this->addFlash('error', 'Select a valid room type.');
        } elseif ($number === '' || $price < 1) {
            $this->addFlash('error', 'Room number, type and a nightly rate are required.');
        } elseif ($em->getRepository(Room::class)->findOneBy(['branch' => $branch, 'number' => $number])) {
            $this->addFlash('error', sprintf('Room %s already exists in this branch.', $number));
        } else {
            $em->persist((new Room())->setBranch($branch)->setNumber($number)->setType($type)
                ->setCapacity(max(1, (int) $p->get('capacity', 2)))->setPricePerNight($price)
                ->setAmenities(trim((string) $p->get('amenities')))->setDescription(trim((string) $p->get('description'))));
            $em->flush();
            $this->addFlash('success', sprintf('Room %s was added.', $number));
        }

        return $this->redirectToRoute('app_staff', ['branch' => $branch->getId()]);
    }

    #[Route('/room/{id}/update', name: 'app_staff_room_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateRoom(Room $room, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        $this->assertCan($room->getBranch());
        $price = (int) $request->request->get('price');
        if ($price > 0) $room->setPricePerNight($price);
        $roomRepo = $em->getRepository(Room::class);
        if ($roomRepo->hasActiveBooking($room)) {
            $room->setActive(false);
            $this->addFlash('error', sprintf('Room %s has an active reservation and cannot be opened.', $room->getNumber()));
        } else {
            $room->setActive($request->request->getBoolean('active'));
        }
        $em->flush();
        $this->addFlash('success', sprintf('Room %s was updated.', $room->getNumber()));

        return $this->redirectToRoute('app_staff', ['branch' => $room->getBranch()->getId()]);
    }

    private function assertCan(Branch $branch): void
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $this->me()->getBranch()?->getId() !== $branch->getId()) {
            throw $this->createAccessDeniedException('This reservation belongs to another branch.');
        }
    }
}
