<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Room;
use App\Repository\ReservationRepository;
use App\Repository\RoomRepository;
use App\Util\Dates;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AppController
{
    #[Route('/book/{id}', name: 'app_book', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function book(Room $room, Request $request, RoomRepository $rooms, EntityManagerInterface $em): Response
    {
        $src = $request->isMethod('POST') ? $request->request : $request->query;
        $today = new \DateTimeImmutable('today');
        $stayType = in_array((string) $src->get('stay_type'), ['short', 'long'], true) ? (string) $src->get('stay_type') : 'long';
        $parseCheckIn = static function (mixed $value) use ($today): \DateTimeImmutable {
            if (is_string($value)) {
                $value = str_replace('T', ' ', trim($value));
                if (preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2})?$/', $value)) {
                    $date = $value === substr($value, 0, 10) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
                    if ($date) return $date;
                }
            }
            return $today->modify('+1 day');
        };
        $in = $parseCheckIn($src->get('in'));
        $out = Dates::parse($src->get('out')) ?? $in->modify('+2 days');
        $shortHours = max(3, min(24, (int) $src->get('short_hours', 3)));
        if (!in_array($shortHours, [3, 6, 9, 12, 24], true)) $shortHours = 3;
        if ($stayType === 'short') $out = $in->modify('+' . $shortHours . ' hours');
        $guests = max(1, (int) $src->get('guests', 1));
        $additionalGuests = max(0, min(max(0, $room->getCapacity() - 1), (int) $src->get('additional_guests', 0)));
        $guests = 1 + $additionalGuests;
        $notes = trim((string) $request->request->get('notes'));
        $nights = $out > $in && $stayType === 'long' ? (int) $in->diff($out)->days : 0;

        $extraPrices = ['towel' => 150, 'toothbrush' => 50, 'slippers' => 100, 'water' => 80, 'pillow' => 200];
        $selectedExtras = [];
        $extrasTotal = 0;
        foreach ($extraPrices as $key => $price) {
            $quantity = max(0, min(10, (int) ((is_array($src->all('extra_qty')) ? ($src->all('extra_qty')[$key] ?? 0) : 0))));
            $selectedExtras[$key] = $quantity;
            $extrasTotal += $quantity * $price;
        }
        $personsTotal = $additionalGuests * 500;
        $roomSubtotal = $stayType === 'short'
            ? (int) ceil($room->getPricePerNight() / 24 * $shortHours)
            : $nights * $room->getPricePerNight();
        $discountType = (string) $src->get('discount_type', 'none');
        if (!in_array($discountType, ['none', 'pwd', 'senior'], true)) $discountType = 'none';
        $discountTotal = in_array($discountType, ['pwd', 'senior'], true)
            ? (int) round(($roomSubtotal + $personsTotal + $extrasTotal) * 0.20)
            : 0;
        $total = max(0, $roomSubtotal + $personsTotal + $extrasTotal - $discountTotal);

        $errors = [];
        if (!$room->isActive()) $errors[] = 'This room is not open for booking right now.';
        if ($in < $today) $errors[] = 'Check-in cannot be in the past.';
        if ($stayType === 'long' && $out <= $in) $errors[] = 'Check-out must come after check-in.';
        if ($stayType === 'short' && $out <= $in) $errors[] = 'Short stay duration is invalid.';
        if ($request->isMethod('POST') && !$request->request->getBoolean('rules_accepted')) $errors[] = 'Please accept the Rules and Regulations before confirming.';
        if (!$errors && !$rooms->isFree($room, $in, $out)) {
            $errors[] = 'This room is already booked for those dates and times. Pick another time or another room.';
        }

        if ($request->isMethod('POST')) {
            $this->csrf($request);
            if (!$errors) {
                $extraSummary = [];
                foreach ($selectedExtras as $key => $quantity) if ($quantity > 0) $extraSummary[] = sprintf('%d x %s', $quantity, ucfirst($key));
                $r = (new Reservation())->setGuest($this->me())->setRoom($room)->setCheckIn($in)->setCheckOut($out)
                    ->setGuests($guests)->setTotal($total)->setNotes($notes ?: (count($extraSummary) ? implode(', ', $extraSummary) : null));
                $em->persist($r);
                $room->setActiveBooking(true);
                $em->flush();
                $this->addFlash('success', sprintf('Reservation %s is held. Complete payment to confirm it.', $r->getReference()));

                return $this->redirectToRoute('app_payment', ['id' => $r->getId()]);
            }
        }

        $availableRoomCount = count($rooms->findAvailable($room->getBranch(), $in, $out, $guests));
        return $this->render('reservation/book.html.twig', compact('room', 'in', 'out', 'guests', 'notes', 'nights', 'errors', 'today', 'stayType', 'shortHours', 'additionalGuests', 'selectedExtras', 'roomSubtotal', 'personsTotal', 'extrasTotal', 'discountType', 'discountTotal', 'total', 'availableRoomCount'));
    }

    #[Route('/payment/{id}', name: 'app_payment', requirements: ['id' => '\d+'])]
    public function payment(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        if ($reservation->getGuest() !== $this->me()) throw $this->createAccessDeniedException();
        if ($reservation->getStatus() !== 'pending' && !$request->isMethod('POST')) {
            return $this->redirectToRoute('app_account');
        }
        if ($request->isMethod('POST')) {
            $this->csrf($request);
            $method = (string) $request->request->get('payment_method', 'instapay');
            $reservation->setNotes(trim((string) $reservation->getNotes()) . ' | Payment completed: ' . ucfirst($method));
            $reservation->setStatus('confirmed');
            $em->flush();
            $this->addFlash('success', 'Payment completed. Your reservation is confirmed.');
            return $this->redirectToRoute('app_account');
        }
        return $this->render('reservation/payment.html.twig', ['reservation' => $reservation]);
    }

    #[Route('/account', name: 'app_account')]
    public function account(ReservationRepository $repo): Response
    {
        $list = $repo->findBy(['guest' => $this->me()], ['checkIn' => 'DESC']);
        $today = new \DateTimeImmutable('today');
        $upcoming = array_filter($list, fn (Reservation $r) => in_array($r->getStatus(), ['pending', 'confirmed'], true) && $r->getCheckOut() >= $today);
        $spent = array_sum(array_map(fn (Reservation $r) => in_array($r->getStatus(), ['confirmed', 'checked_in', 'checked_out'], true) ? $r->getTotal() : 0, $list));

        return $this->render('account/dashboard.html.twig', [
            'reservations' => $list, 'upcoming' => count($upcoming), 'spent' => $spent,
            'completed' => count(array_filter($list, fn (Reservation $r) => $r->getStatus() === 'checked_out')),
        ]);
    }

    #[Route('/account/cancel/{id}', name: 'app_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        if ($reservation->getGuest() !== $this->me()) throw $this->createAccessDeniedException();
        if ($reservation->isCancellable()) {
            $reservation->setStatus('cancelled');
            $rooms = $em->getRepository(Room::class);
            $room = $reservation->getRoom();
            $room->setActiveBooking($rooms->hasActiveBooking($room));
            $em->flush();
            $this->addFlash('success', sprintf('Reservation %s was cancelled.', $reservation->getReference()));
        } else {
            $this->addFlash('error', 'This reservation can no longer be cancelled online. Please contact the hotel.');
        }

        return $this->redirectToRoute('app_account');
    }
}
