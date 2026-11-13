<?php

namespace App\Controller;

use App\Entity\Branch;
use App\Entity\Room;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\MaintenanceRequest;
use App\Entity\InventoryItem;
use App\Entity\AuditLog;
use App\Repository\ReservationRepository;
use App\Repository\RoomRepository;
use App\Util\Dates;
use App\Security\GuestLoginAttemptGuard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ApiController extends AppController
{
    #[Route('/api/csrf/{tokenId}', name: 'api_csrf', methods: ['GET'])]
    public function csrfToken(string $tokenId, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        return $this->json(['token' => $tokens->getToken($tokenId)->getValue()]);
    }

    #[Route('/api/session', name: 'api_session', methods: ['GET'])]
    public function session(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) return $this->json(['data' => null]);
        return $this->json(['data' => ['name' => $user->getFullName(), 'role' => $user->getPrimaryRole(), 'roles' => $user->getRoles()]]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        \Symfony\Component\HttpFoundation\Request $request,
        \Doctrine\ORM\EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        CsrfTokenManagerInterface $tokens,
    ): JsonResponse {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['error' => 'Invalid security token.'], 403);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $confirmPassword = (string) ($payload['passwordConfirm'] ?? '');
        $errors = [];
        if ($name === '') $errors[] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 8) $errors[] = 'Your password needs at least 8 characters.';
        if ($password !== $confirmPassword) $errors[] = 'Your passwords do not match.';
        if (($payload['terms'] ?? false) !== true) $errors[] = 'Please accept the Terms and Privacy Policy.';
        if ($em->getRepository(User::class)->findOneBy(['email' => $email])) $errors[] = 'An account with this email already exists.';
        if ($errors) return $this->json(['errors' => $errors], 422);

        $user = (new User())->setEmail($email)->setFullName($name)->setPhone($phone ?: null);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();
        return $this->json(['data' => ['message' => 'Your account is ready. You can sign in now.']], 201);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        \Symfony\Component\HttpFoundation\Request $request,
        \Doctrine\ORM\EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        CsrfTokenManagerInterface $tokens,
        Security $security,
        GuestLoginAttemptGuard $loginAttempts,
    ): JsonResponse {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('authenticate', (string) ($payload['_token'] ?? '')))) {
            return $this->json(['error' => 'Invalid security token.'], 403);
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user instanceof User && ($lockedUntil = $loginAttempts->lockoutUntil($user))) {
            $retryAfter = max(1, (int) ceil(($lockedUntil->getTimestamp() - time()) / 60));
            return $this->json(['error' => sprintf('Too many failed sign-in attempts. Please try again in %d minutes.', $retryAfter)], 429);
        }
        if (!$user || !$hasher->isPasswordValid($user, (string) ($payload['password'] ?? ''))) {
            if ($user instanceof User) {
                $loginAttempts->recordFailure($user);
            }
            return $this->json(['error' => 'The email or password is incorrect.'], 401);
        }

        $loginAttempts->recordSuccess($user);
        $security->login($user, 'form_login');
        $target = $user->getPrimaryRole() === 'ROLE_SUPER_ADMIN' ? '/super' : ($user->getPrimaryRole() === 'ROLE_STAFF' || $user->getPrimaryRole() === 'ROLE_ADMIN' ? '/staff' : '/account');
        return $this->json(['data' => ['target' => $target, 'name' => $user->getFullName()]]);
    }

    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgotPassword(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $user = $em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim((string) ($payload['email'] ?? '')))]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $user->setResetToken(hash('sha256', $token))->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
            $em->flush();
            return $this->json(['data' => ['message' => 'If the account exists, a password reset link has been issued.', 'developmentToken' => $this->getParameter('kernel.environment') === 'dev' ? $token : null]]);
        }
        return $this->json(['data' => ['message' => 'If the account exists, a password reset link has been issued.', 'developmentToken' => null]]);
    }

    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function resetPassword(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $hash = hash('sha256', (string) ($payload['token'] ?? ''));
        $user = $em->getRepository(User::class)->findOneBy(['resetToken' => $hash]);
        $password = (string) ($payload['password'] ?? '');
        if (!$user || !$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() <= new \DateTimeImmutable() || strlen($password) < 8) return $this->json(['error' => 'Reset link is invalid, expired, or password is too short.'], 422);
        $user->setPassword($hasher->hashPassword($user, $password))->setResetToken(null)->setResetTokenExpiresAt(null);
        $em->flush();
        return $this->json(['data' => ['message' => 'Password updated successfully.']]);
    }

    #[Route('/api/branches', name: 'api_branches', methods: ['GET'])]
    public function branches(Request $request, \Doctrine\ORM\EntityManagerInterface $em, RoomRepository $rooms): JsonResponse
    {
        $window = $this->availabilityWindow($request);
        if ($window instanceof JsonResponse) return $window;
        [$checkIn, $checkOut, $guests] = $window;

        $branches = [];
        foreach ($em->getRepository(Branch::class)->findBy(['active' => true], ['name' => 'ASC']) as $branch) {
            $availableRooms = $rooms->findAvailable($branch, $checkIn, $checkOut, $guests);
            $prices = array_map(static fn (Room $room): int => $room->getPricePerNight(), $availableRooms);
            $branches[] = [
                'id' => $branch->getId(),
                'name' => $branch->getName(),
                'city' => $branch->getCity(),
                'address' => $branch->getAddress(),
                'phone' => $branch->getPhone(),
                'tagline' => $branch->getTagline(),
                'description' => $branch->getDescription(),
                'minPrice' => $prices ? min($prices) : null,
                'availableRooms' => count($availableRooms),
            ];
        }

        return $this->json(['data' => $branches, 'availability' => [
            'checkIn' => $checkIn->format('Y-m-d'),
            'checkOut' => $checkOut->format('Y-m-d'),
            'guests' => $guests,
        ]]);
    }

    #[Route('/api/branches/{id}/rooms', name: 'api_branch_rooms', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function rooms(Branch $branch, Request $request, RoomRepository $rooms): JsonResponse
    {
        if (!$branch->isActive()) return $this->json(['error' => 'Hotel not found.'], 404);
        $window = $this->availabilityWindow($request);
        if ($window instanceof JsonResponse) return $window;
        [$in, $out, $guests] = $window;
        $data = array_map(static fn (Room $room) => [
            'id' => $room->getId(),
            'number' => $room->getNumber(),
            'type' => $room->getType(),
            'capacity' => $room->getCapacity(),
            'pricePerNight' => $room->getPricePerNight(),
            'amenities' => array_values(array_filter(array_map('trim', explode(',', $room->getAmenities())))),
            'description' => $room->getDescription(),
            'active' => $room->isActive(),
        ], $rooms->findAvailable($branch, $in, $out, $guests));

        return $this->json([
            'branch' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'city' => $branch->getCity(), 'address' => $branch->getAddress(), 'phone' => $branch->getPhone(), 'description' => $branch->getDescription()],
            'data' => $data,
            'availability' => ['checkIn' => $in->format('Y-m-d'), 'checkOut' => $out->format('Y-m-d'), 'guests' => $guests],
        ]);
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: int}|JsonResponse */
    private function availabilityWindow(Request $request): array|JsonResponse
    {
        $today = new \DateTimeImmutable('today');
        $checkInValue = $request->query->get('checkIn');
        $checkOutValue = $request->query->get('checkOut');
        $checkIn = $checkInValue === null ? $today->modify('+1 day') : Dates::parse($checkInValue);
        $checkOut = $checkOutValue === null ? $checkIn?->modify('+1 day') : Dates::parse($checkOutValue);
        if ($checkIn) $checkIn = $checkIn->setTime(14, 0);
        if ($checkOut) $checkOut = $checkOut->setTime(12, 0);
        $guests = filter_var($request->query->get('guests', '1'), FILTER_VALIDATE_INT);

        if (
            !$checkIn || !$checkOut ||
            $checkIn->format('Y-m-d') !== $checkInValue && $checkInValue !== null ||
            $checkOut->format('Y-m-d') !== $checkOutValue && $checkOutValue !== null ||
            $checkIn < $today || $checkOut <= $checkIn ||
            $checkOut > $checkIn->modify('+30 days') ||
            $guests === false || $guests < 1 || $guests > 10
        ) {
            return $this->json(['error' => 'Choose valid dates within the next 30 nights and between 1 and 10 guests.'], 422);
        }

        return [$checkIn, $checkOut, $guests];
    }

    #[Route('/api/rooms/{id}', name: 'api_room', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function room(Room $room): JsonResponse
    {
        if (!$room->getBranch()?->isActive()) return $this->json(['error' => 'Room not found.'], 404);
        return $this->json(['data' => [
            'id' => $room->getId(),
            'branchId' => $room->getBranch()?->getId(),
            'branchName' => $room->getBranch()?->getName(),
            'number' => $room->getNumber(),
            'type' => $room->getType(),
            'capacity' => $room->getCapacity(),
            'pricePerNight' => $room->getPricePerNight(),
            'amenities' => array_values(array_filter(array_map('trim', explode(',', $room->getAmenities())))),
            'description' => $room->getDescription(),
            'active' => $room->isActive(),
        ]]);
    }

    #[Route('/api/reservations/{id}', name: 'api_reservation', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function reservation(Reservation $reservation): JsonResponse
    {
        if ($reservation->getGuest() !== $this->me()) return $this->json(['error' => 'Reservation not found.'], 404);
        return $this->json(['data' => $this->reservationData($reservation)]);
    }

    #[Route('/api/account', name: 'api_account', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function account(ReservationRepository $reservations): JsonResponse
    {
        $list = $reservations->findBy(['guest' => $this->me()], ['checkIn' => 'DESC']);
        $upcoming = array_filter($list, static fn (Reservation $r) => in_array($r->getStatus(), ['pending', 'confirmed'], true));
        $spent = array_sum(array_map(static fn (Reservation $r) => in_array($r->getStatus(), ['confirmed', 'checked_in', 'checked_out'], true) ? $r->getTotal() : 0, $list));

        return $this->json(['data' => [
            'guest' => $this->me()->getFullName(),
            'stats' => ['upcoming' => count($upcoming), 'completed' => count(array_filter($list, static fn (Reservation $r) => $r->getStatus() === 'checked_out')), 'spent' => $spent],
            'reservations' => array_map([$this, 'reservationData'], $list),
        ]]);
    }

    #[Route('/api/staff', name: 'api_staff', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function staff(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, ReservationRepository $reservations, RoomRepository $rooms): JsonResponse
    {
        $branch = $this->isGranted('ROLE_SUPER_ADMIN') ? $em->getRepository(Branch::class)->find((int) $request->query->get('branch')) : $this->me()->getBranch();
        if (!$branch) return $this->json(['error' => 'No branch is assigned to this account.'], 400);
        $status = (string) $request->query->get('status');
        $status = in_array($status, Reservation::STATUSES, true) ? $status : null;
        $list = $reservations->forBranch($branch, $status);
        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ($query !== '') $list = array_values(array_filter($list, static fn (Reservation $r) => str_contains(mb_strtolower(implode(' ', [$r->getReference(), $r->getGuest()->getFullName(), $r->getGuest()->getEmail(), $r->getRoom()->getNumber()])), $query)));
        return $this->json(['data' => ['role' => $this->me()->getPrimaryRole(), 'permissions' => ['manageRooms' => $this->isGranted('ROLE_ADMIN')], 'branch' => ['id' => $branch->getId(), 'name' => $branch->getName()], 'stats' => ['arrivals' => $reservations->countFor($branch, ['pending', 'confirmed'], new \DateTimeImmutable('today')), 'inhouse' => $reservations->countFor($branch, ['checked_in']), 'pending' => $reservations->countFor($branch, ['pending']), 'revenue' => $reservations->revenueFor($branch)], 'reservations' => array_map([$this, 'reservationData'], $list), 'rooms' => array_map(static fn (Room $r) => ['id' => $r->getId(), 'number' => $r->getNumber(), 'type' => $r->getType(), 'capacity' => $r->getCapacity(), 'pricePerNight' => $r->getPricePerNight(), 'active' => $r->isActive()], $rooms->findBy(['branch' => $branch], ['number' => 'ASC']))]]);
    }

    #[Route('/api/super', name: 'api_super', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function super(\Doctrine\ORM\EntityManagerInterface $em, ReservationRepository $reservations): JsonResponse
    {
        $branches = $em->getRepository(Branch::class)->findBy([], ['name' => 'ASC']);
        $users = $em->getRepository(User::class)->findBy([], ['id' => 'DESC']);
        $rooms = $em->getRepository(Room::class)->findBy([], ['number' => 'ASC']);
        return $this->json(['data' => ['totals' => ['revenue' => $reservations->revenueFor(null), 'reservations' => $reservations->countFor(null, Reservation::STATUSES), 'pending' => $reservations->countFor(null, ['pending']), 'users' => count($users)], 'branches' => array_map(fn (Branch $b) => ['id' => $b->getId(), 'name' => $b->getName(), 'city' => $b->getCity(), 'address' => $b->getAddress(), 'phone' => $b->getPhone(), 'active' => $b->isActive(), 'rooms' => count($b->getRooms()), 'inhouse' => $reservations->countFor($b, ['checked_in']), 'pending' => $reservations->countFor($b, ['pending']), 'revenue' => $reservations->revenueFor($b)], $branches), 'users' => array_map(static fn (User $u) => ['id' => $u->getId(), 'name' => $u->getFullName(), 'email' => $u->getEmail(), 'role' => $u->getPrimaryRoleLabel(), 'branch' => $u->getBranch()?->getName()], $users), 'rooms' => array_map(static fn (Room $room) => ['id' => $room->getId(), 'branch' => $room->getBranch()?->getName() ?? 'No branch', 'number' => $room->getNumber(), 'type' => $room->getType(), 'status' => $room->getHousekeepingStatus(), 'notes' => $room->getHousekeepingNotes()], $rooms), 'recent' => array_map([$this, 'reservationData'], $reservations->forBranch(null, null, 12))]]);
    }

    #[Route('/api/reservations/{id}/cancel', name: 'api_reservation_cancel', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelReservation(Reservation $reservation, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, RoomRepository $rooms, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        if ($reservation->getGuest() !== $this->me() || !$reservation->isCancellable()) return $this->json(['error' => 'This reservation cannot be cancelled.'], 403);
        $reservation->setStatus('cancelled'); $reservation->getRoom()->setActiveBooking($rooms->hasActiveBooking($reservation->getRoom())); $em->flush();
        return $this->json(['data' => $this->reservationData($reservation)]);
    }

    #[Route('/api/staff/reservations/{id}/status', name: 'api_staff_reservation_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function updateReservationStatus(Reservation $reservation, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, RoomRepository $rooms, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $branch = $reservation->getRoom()->getBranch();
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $this->me()->getBranch()?->getId() !== $branch->getId()) return $this->json(['error' => 'You cannot update this branch.'], 403);
        $status = (string) ($payload['status'] ?? '');
        if (!in_array($status, $reservation->getNextStatuses(), true)) return $this->json(['error' => 'That status change is not allowed.'], 422);
        if ($status === 'checked_in') {
            $reservation->setActualCheckIn(new \DateTimeImmutable())->setGuestIdType(trim((string) ($payload['guestIdType'] ?? '')) ?: null)->setGuestIdNumber(trim((string) ($payload['guestIdNumber'] ?? '')) ?: null);
            $reservation->getRoom()->setHousekeepingStatus('occupied');
        }
        if ($status === 'checked_out') {
            $reservation->setActualCheckOut(new \DateTimeImmutable());
            $reservation->getRoom()->setHousekeepingStatus('dirty');
        }
        $reservation->setStatus($status); $rooms->synchronizeAvailability($reservation->getRoom());
        $this->audit($em, $request, 'reservation_status_changed', $reservation->getReference(), $status);
        $em->flush();
        return $this->json(['data' => $this->reservationData($reservation)]);
    }

    #[Route('/api/rooms/{id}/reservations', name: 'api_reservation_create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createReservation(Room $room, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, RoomRepository $rooms, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        if (!$room->getBranch()?->isActive()) return $this->json(['error' => 'This hotel is no longer accepting reservations.'], 404);
        $today = new \DateTimeImmutable('today');
        $stayType = in_array((string) ($payload['stayType'] ?? 'long'), ['short', 'long'], true) ? (string) $payload['stayType'] : 'long';
        try {
            $in = new \DateTimeImmutable((string) ($payload['checkIn'] ?? ''));
            $out = $stayType === 'short' ? $in->modify('+' . max(3, min(24, (int) ($payload['shortHours'] ?? 3))) . ' hours') : new \DateTimeImmutable((string) ($payload['checkOut'] ?? ''));
            if ($stayType === 'long' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($payload['checkOut'] ?? ''))) {
                $out = $out->setTime(12, 0);
            }
        } catch (\Exception) { return $this->json(['error' => 'Check-in and check-out dates are required.'], 422); }
        $additionalGuests = max(0, min(max(0, $room->getCapacity() - 1), (int) ($payload['additionalGuests'] ?? 0)));
        $guests = 1 + $additionalGuests;
        if (!$room->isActive() || $in < $today || ($stayType === 'long' && $out->format('Y-m-d') <= $in->format('Y-m-d')) || ($stayType === 'short' && $out <= $in)) return $this->json(['error' => 'The selected room or stay dates are invalid.'], 422);
        if (!(bool) ($payload['rulesAccepted'] ?? false)) return $this->json(['error' => 'You must accept the hotel rules.'], 422);
        if (!$rooms->isFree($room, $in, $out)) return $this->json(['error' => 'This room is already booked for those dates.'], 409);
        $prices = ['towel' => 150, 'toothbrush' => 50, 'slippers' => 100, 'water' => 80, 'pillow' => 200];
        $extrasTotal = 0; $extraSummary = [];
        foreach ($prices as $key => $price) { $quantity = max(0, min(10, (int) (($payload['extras'][$key] ?? 0)))); $extrasTotal += $quantity * $price; if ($quantity > 0) $extraSummary[] = sprintf('%d x %s', $quantity, ucfirst($key)); }
        $nights = $stayType === 'long' ? max(1, (int) $in->setTime(0, 0)->diff($out->setTime(0, 0))->format('%a')) : 0;
        $hours = max(3, min(24, (int) ($payload['shortHours'] ?? 3)));
        $branchName = $room->getBranch()->getName();
        $roomType = strtolower($room->getType());
        $exactShortRates = null;
        if (str_contains($branchName, 'Amor Loco') && str_contains($roomType, 'tryke')) {
            $exactShortRates = [2 => 290, 3 => 330, 6 => 600, 12 => 1100, 24 => 1850];
        } elseif (str_contains($branchName, 'Amor Loco') && str_contains($roomType, 'standard')) {
            $exactShortRates = [3 => 400, 6 => 660, 12 => 1250, 24 => 1950];
        } elseif (str_contains($branchName, 'Mi Amor') && (str_contains($roomType, 'tryke') || str_contains($roomType, 'trike'))) {
            $exactShortRates = [3 => 300, 6 => 550, 12 => 1200, 24 => 1700];
        } elseif (str_contains($branchName, 'Mi Amor') && (str_contains($roomType, 'executive') || str_contains($roomType, 'standard'))) {
            $exactShortRates = [3 => 450, 6 => 850, 12 => 1500, 24 => 2000];
        }
        $roomSubtotal = $stayType === 'short'
            ? (int) ($exactShortRates[$hours] ?? ceil($room->getPricePerNight() / 24 * $hours))
            : $nights * $room->getPricePerNight();
        $personsTotal = $additionalGuests * 500;
        $discountType = in_array((string) ($payload['discountType'] ?? 'none'), ['pwd', 'senior'], true) ? (string) $payload['discountType'] : 'none';
        $discountTotal = $discountType === 'none' ? 0 : (int) round(($roomSubtotal + $personsTotal + $extrasTotal) * .20);
        $total = max(0, $roomSubtotal + $personsTotal + $extrasTotal - $discountTotal);
        $notes = trim((string) ($payload['notes'] ?? '')) ?: (count($extraSummary) ? implode(', ', $extraSummary) : null);
        $reservation = (new Reservation())->setGuest($this->me())->setRoom($room)->setCheckIn($in)->setCheckOut($out)->setGuests($guests)->setTotal($total)->setNotes($notes);
        $em->persist($reservation); $room->setActiveBooking(true); $em->flush();
        return $this->json(['data' => ['id' => $reservation->getId(), 'reference' => $reservation->getReference(), 'total' => $reservation->getTotal(), 'status' => $reservation->getStatus(), 'paymentUrl' => '/payment/' . $reservation->getId()]], 201);
    }

    #[Route('/api/reservations/{id}/pay', name: 'api_reservation_pay', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function payReservation(Reservation $reservation, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        if ($reservation->getGuest() !== $this->me() || $reservation->getStatus() !== 'pending') return $this->json(['error' => 'This reservation is not available for payment.'], 422);
        $method = in_array((string) ($payload['paymentMethod'] ?? ''), ['instapay', 'credit-card'], true) ? (string) $payload['paymentMethod'] : 'instapay';
        $reservation->setNotes(trim((string) $reservation->getNotes()) . ' | Payment completed: ' . ucfirst($method))->setStatus('confirmed'); $em->flush();
        return $this->json(['data' => $this->reservationData($reservation)]);
    }

    #[Route('/api/staff/rooms/{id}', name: 'api_staff_room_update', requirements: ['id' => '\\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateRoom(Room $room, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, RoomRepository $rooms, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $this->me()->getBranch()?->getId() !== $room->getBranch()?->getId()) return $this->json(['error' => 'You cannot update this branch.'], 403);
        $price = (int) ($payload['pricePerNight'] ?? 0); if ($price > 0) $room->setPricePerNight($price);
        if ($rooms->hasActiveBooking($room)) $room->setActive(false); else $room->setActive((bool) ($payload['active'] ?? false));
        $em->flush();
        return $this->json(['data' => ['id' => $room->getId(), 'number' => $room->getNumber(), 'pricePerNight' => $room->getPricePerNight(), 'active' => $room->isActive()]]);
    }

    #[Route('/api/staff/rooms', name: 'api_staff_room_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createRoom(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $branch = $em->getRepository(Branch::class)->find((int) ($payload['branchId'] ?? 0));
        if (!$branch || (!$this->isGranted('ROLE_SUPER_ADMIN') && $this->me()->getBranch()?->getId() !== $branch->getId())) return $this->json(['error' => 'You cannot add a room to this branch.'], 403);
        $number = trim((string) ($payload['number'] ?? '')); $type = trim((string) ($payload['type'] ?? '')); $price = (int) ($payload['pricePerNight'] ?? 0);
        if ($number === '' || !in_array($type, ['Tryke Room', 'Standard Room'], true) || $price < 1) return $this->json(['error' => 'Room number, type, and price are required.'], 422);
        if ($em->getRepository(Room::class)->findOneBy(['branch' => $branch, 'number' => $number])) return $this->json(['error' => 'That room number already exists.'], 409);
        $room = (new Room())->setBranch($branch)->setNumber($number)->setType($type)->setCapacity(max(1, (int) ($payload['capacity'] ?? 2)))->setPricePerNight($price)->setAmenities(trim((string) ($payload['amenities'] ?? '')))->setDescription(trim((string) ($payload['description'] ?? '')));
        $em->persist($room); $em->flush();
        return $this->json(['data' => ['id' => $room->getId(), 'number' => $room->getNumber()]], 201);
    }

    #[Route('/api/super/branches', name: 'api_super_branch_create', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function createBranch(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $name = trim((string) ($payload['name'] ?? '')); $city = trim((string) ($payload['city'] ?? ''));
        if ($name === '' || $city === '') return $this->json(['error' => 'Branch name and city are required.'], 422);
        $branch = (new Branch())->setName($name)->setCity($city)->setAddress(trim((string) ($payload['address'] ?? '')))->setPhone(trim((string) ($payload['phone'] ?? '')))->setTagline(trim((string) ($payload['tagline'] ?? '')))->setDescription(trim((string) ($payload['description'] ?? '')));
        $em->persist($branch); $em->flush();
        return $this->json(['data' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'city' => $branch->getCity()]], 201);
    }

    #[Route('/api/super/branches/{id}', name: 'api_super_branch_update', requirements: ['id' => '\\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function updateBranch(Branch $branch, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $name = trim((string) ($payload['name'] ?? '')); $city = trim((string) ($payload['city'] ?? ''));
        if ($name === '' || $city === '') return $this->json(['error' => 'Branch name and city are required.'], 422);
        $branch->setName($name)->setCity($city)->setAddress(trim((string) ($payload['address'] ?? $branch->getAddress())))->setPhone(trim((string) ($payload['phone'] ?? $branch->getPhone())))->setTagline(trim((string) ($payload['tagline'] ?? $branch->getTagline())))->setDescription(trim((string) ($payload['description'] ?? $branch->getDescription()))); $em->flush();
        return $this->json(['data' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'city' => $branch->getCity()]]);
    }

    #[Route('/api/super/users', name: 'api_super_user_create', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function createUser(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $email = trim((string) ($payload['email'] ?? '')); $name = trim((string) ($payload['name'] ?? '')); $password = (string) ($payload['password'] ?? ''); $role = (string) ($payload['role'] ?? 'ROLE_STAFF');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 8 || !isset(User::ROLES[$role])) return $this->json(['error' => 'Name, valid email, password of 8+ characters, and a valid role are required.'], 422);
        if ($em->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)])) return $this->json(['error' => 'That email is already registered.'], 409);
        $branch = !empty($payload['branchId']) ? $em->getRepository(Branch::class)->find((int) $payload['branchId']) : null;
        if (in_array($role, ['ROLE_STAFF', 'ROLE_ADMIN'], true) && (!$branch || !$branch->isActive())) return $this->json(['error' => 'Staff and admins need an active branch.'], 422);
        $user = (new User())->setEmail($email)->setFullName($name)->setRoles($role === 'ROLE_USER' ? [] : [$role])->setBranch($role === 'ROLE_SUPER_ADMIN' ? null : $branch); $user->setPassword($hasher->hashPassword($user, $password)); $em->persist($user); $em->flush();
        return $this->json(['data' => ['id' => $user->getId(), 'name' => $user->getFullName(), 'role' => $user->getPrimaryRoleLabel()]], 201);
    }

    #[Route('/api/super/users/{id}', name: 'api_super_user_update', requirements: ['id' => '\\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function updateUser(User $user, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $role = (string) ($payload['role'] ?? 'ROLE_USER');
        if (!isset(User::ROLES[$role])) return $this->json(['error' => 'Unknown role.'], 422);
        if ($user === $this->me() && $role !== 'ROLE_SUPER_ADMIN') return $this->json(['error' => 'You cannot remove your own super-admin access.'], 422);
        $branch = !empty($payload['branchId']) ? $em->getRepository(Branch::class)->find((int) $payload['branchId']) : null;
        if (in_array($role, ['ROLE_STAFF', 'ROLE_ADMIN'], true) && (!$branch || !$branch->isActive())) return $this->json(['error' => 'Staff and admins need an active branch.'], 422);
        $user->setRoles($role === 'ROLE_USER' ? [] : [$role])->setBranch($role === 'ROLE_SUPER_ADMIN' ? null : $branch); $em->flush();
        return $this->json(['data' => ['id' => $user->getId(), 'name' => $user->getFullName(), 'role' => $user->getPrimaryRoleLabel(), 'branch' => $user->getBranch()?->getName()]]);
    }

    #[Route('/api/staff/operations', name: 'api_staff_operations', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function staffOperations(\Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $rooms = array_map(static fn (Room $room) => ['id' => $room->getId(), 'number' => $room->getNumber(), 'status' => $room->getHousekeepingStatus(), 'notes' => $room->getHousekeepingNotes()], $em->getRepository(Room::class)->findBy(['branch' => $this->me()->getBranch()], ['number' => 'ASC']));
        $maintenanceItems = array_filter($em->getRepository(MaintenanceRequest::class)->findBy([], ['createdAt' => 'DESC'], 100), fn (MaintenanceRequest $item) => $this->isGranted('ROLE_SUPER_ADMIN') || $item->getRoom()->getBranch() === $this->me()->getBranch());
        $maintenance = array_map(static fn (MaintenanceRequest $item) => ['id' => $item->getId(), 'room' => $item->getRoom()->getNumber(), 'title' => $item->getTitle(), 'details' => $item->getDetails(), 'priority' => $item->getPriority(), 'status' => $item->getStatus(), 'createdAt' => $item->getCreatedAt()->format(DATE_ATOM)], array_slice($maintenanceItems, 0, 50));
        $inventory = array_map(static fn (InventoryItem $item) => ['id' => $item->getId(), 'name' => $item->getName(), 'category' => $item->getCategory(), 'quantity' => $item->getQuantity(), 'minimum' => $item->getMinimumQuantity(), 'unit' => $item->getUnit()], $em->getRepository(InventoryItem::class)->findBy(['branch' => $this->me()->getBranch()], ['name' => 'ASC']));
        $audit = array_map(static fn (AuditLog $item) => ['id' => $item->getId(), 'actor' => $item->getActor()?->getFullName() ?: 'System', 'action' => $item->getAction(), 'subject' => $item->getSubject(), 'details' => $item->getDetails(), 'createdAt' => $item->getCreatedAt()->format(DATE_ATOM)], $em->getRepository(AuditLog::class)->findBy([], ['createdAt' => 'DESC'], 50));
        return $this->json(['data' => ['rooms' => $rooms, 'maintenance' => $maintenance, 'inventory' => $inventory, 'audit' => $audit]]);
    }

    #[Route('/api/staff/rooms/{id}/housekeeping', name: 'api_staff_housekeeping', requirements: ['id' => '\\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_STAFF')]
    public function updateHousekeeping(Room $room, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, RoomRepository $rooms, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        if ($room->getBranch() !== $this->me()->getBranch() && !$this->isGranted('ROLE_SUPER_ADMIN')) return $this->json(['error' => 'Not authorized for this room.'], 403);
        $status = (string) ($payload['status'] ?? '');
        if (!in_array($status, ['clean', 'dirty', 'cleaning', 'occupied', 'inspection', 'maintenance'], true)) return $this->json(['error' => 'Invalid housekeeping status.'], 422);
        $room->setHousekeepingStatus($status)->setHousekeepingNotes(trim((string) ($payload['notes'] ?? '')) ?: null);
        $rooms->synchronizeAvailability($room);
        $this->audit($em, $request, 'room_housekeeping_updated', 'Room '.$room->getNumber(), $status);
        $em->flush();
        return $this->json(['data' => ['id' => $room->getId(), 'status' => $status]]);
    }

    #[Route('/api/staff/maintenance', name: 'api_staff_maintenance_create', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function createMaintenance(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $room = $em->getRepository(Room::class)->find((int) ($payload['roomId'] ?? 0));
        if (!$room || ($room->getBranch() !== $this->me()->getBranch() && !$this->isGranted('ROLE_SUPER_ADMIN'))) return $this->json(['error' => 'Room not found.'], 404);
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') return $this->json(['error' => 'Maintenance title is required.'], 422);
        $item = (new MaintenanceRequest())->setRoom($room)->setTitle($title)->setDetails(trim((string) ($payload['details'] ?? '')))->setPriority(in_array((string) ($payload['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true) ? $payload['priority'] : 'normal');
        $em->persist($item); $room->setHousekeepingStatus('maintenance');
        $this->audit($em, $request, 'maintenance_created', 'Room '.$room->getNumber(), $title);
        $em->flush();
        return $this->json(['data' => ['id' => $item->getId()]], 201);
    }

    #[Route('/api/staff/maintenance/{id}', name: 'api_staff_maintenance_update', requirements: ['id' => '\\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_STAFF')]
    public function updateMaintenance(MaintenanceRequest $item, \Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        if ($item->getRoom()->getBranch() !== $this->me()->getBranch() && !$this->isGranted('ROLE_SUPER_ADMIN')) return $this->json(['error' => 'Not authorized for this maintenance request.'], 403);
        $status = (string) ($payload['status'] ?? '');
        if (!in_array($status, ['open', 'in_progress', 'completed', 'cancelled'], true)) return $this->json(['error' => 'Invalid maintenance status.'], 422);
        $item->setStatus($status);
        if ($status === 'completed') $item->getRoom()->setHousekeepingStatus('inspection');
        $this->audit($em, $request, 'maintenance_updated', 'Room '.$item->getRoom()->getNumber(), $status);
        $em->flush();
        return $this->json(['data' => ['id' => $item->getId(), 'status' => $status]]);
    }

    #[Route('/api/staff/inventory', name: 'api_staff_inventory', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function saveInventory(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, CsrfTokenManagerInterface $tokens): JsonResponse
    {
        $payload = $request->toArray();
        if (!$tokens->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('app', (string) ($payload['_token'] ?? '')))) return $this->json(['error' => 'Invalid security token.'], 403);
        $item = (new InventoryItem())->setBranch($this->me()->getBranch())->setName(trim((string) ($payload['name'] ?? '')))->setCategory(trim((string) ($payload['category'] ?? 'supplies')))->setQuantity((int) ($payload['quantity'] ?? 0))->setMinimumQuantity((int) ($payload['minimum'] ?? 0))->setUnit(trim((string) ($payload['unit'] ?? 'pcs')));
        if ($item->getName() === '') return $this->json(['error' => 'Item name is required.'], 422);
        $em->persist($item); $this->audit($em, $request, 'inventory_added', $item->getName(), (string) $item->getQuantity());
        $em->flush();
        return $this->json(['data' => ['id' => $item->getId()]], 201);
    }

    private function audit(\Doctrine\ORM\EntityManagerInterface $em, \Symfony\Component\HttpFoundation\Request $request, string $action, string $subject, string $details): void
    {
        $log = (new AuditLog())->setActor($this->me())->setAction($action)->setSubject($subject)->setDetails($details)->setIpAddress($request->getClientIp() ?: 'unknown');
        $em->persist($log);
    }

    private function reservationData(Reservation $reservation): array
    {
        return ['id' => $reservation->getId(), 'reference' => $reservation->getReference(), 'status' => $reservation->getStatus(), 'checkIn' => $reservation->getCheckIn()->format(DATE_ATOM), 'checkOut' => $reservation->getCheckOut()->format(DATE_ATOM), 'actualCheckIn' => $reservation->getActualCheckIn()?->format(DATE_ATOM), 'actualCheckOut' => $reservation->getActualCheckOut()?->format(DATE_ATOM), 'guests' => $reservation->getGuests(), 'total' => $reservation->getTotal(), 'nights' => $reservation->getNights(), 'guest' => $reservation->getGuest()->getFullName(), 'email' => $reservation->getGuest()->getEmail(), 'room' => $reservation->getRoom()->getNumber(), 'roomType' => $reservation->getRoom()->getType(), 'branch' => $reservation->getRoom()->getBranch()->getName()];
    }
}
