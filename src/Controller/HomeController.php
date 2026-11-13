<?php

namespace App\Controller;

use App\Entity\Branch;
use App\Repository\RoomRepository;
use App\Util\Dates;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AppController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em, RoomRepository $rooms): Response
    {
        return $this->render('home/index.html.twig', [
            'branches' => $em->getRepository(Branch::class)->findBy(['active' => true], ['name' => 'ASC']),
            'minPrices' => $rooms->minPrices(),
        ]);
    }

    #[Route('/branch/{id}', name: 'app_branch', requirements: ['id' => '\d+'])]
    public function branch(Branch $branch, Request $request, RoomRepository $rooms): Response
    {
        $today = new \DateTimeImmutable('today');
        $in = Dates::parse($request->query->get('in')) ?? $today->modify('+1 day');
        $notice = null;
        if ($in < $today) $in = $today;
        $out = Dates::parse($request->query->get('out')) ?? $in->modify('+2 days');
        if ($out <= $in) {
            $out = $in->modify('+1 day');
            $notice = 'Check-out must come after check-in, so we moved it to the next day.';
        }
        $guests = max(1, min(10, (int) $request->query->get('guests', 1)));

        $availableRooms = $rooms->findAvailable($branch, $in, $out, $guests);
        $roomGroups = ['Tryke Room' => [], 'Standard Room' => []];
        foreach ($availableRooms as $room) {
            $type = $room->getType() === 'Tryke Room' ? 'Tryke Room' : 'Standard Room';
            $roomGroups[$type][] = $room;
        }

        $roomTypeStats = [];
        foreach ($roomGroups as $type => $typeRooms) {
            $capacities = array_map(fn ($room) => $room->getCapacity(), $typeRooms);
            $representative = $typeRooms[0] ?? null;
            $roomTypeStats[$type] = [
                'count' => count($typeRooms),
                'maxGuests' => $capacities ? max($capacities) : 0,
                'amenities' => $representative ? array_values(array_filter(array_map('trim', explode(',', $representative->getAmenities())))) : [],
                'description' => $representative ? $representative->getDescription() : '',
            ];
        }
        $selectedType = $request->query->get('room_type');
        if (!in_array($selectedType, ['Tryke Room', 'Standard Room'], true)) $selectedType = null;

        return $this->render('home/branch.html.twig', [
            'branch' => $branch, 'in' => $in, 'out' => $out, 'guests' => $guests, 'notice' => $notice,
            'nights' => (int) $in->diff($out)->days, 'today' => $today,
            'rooms' => $availableRooms, 'roomGroups' => $roomGroups, 'roomTypeStats' => $roomTypeStats, 'selectedType' => $selectedType,
        ]);
    }
}
