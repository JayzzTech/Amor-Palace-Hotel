<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    public const STATUSES = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];
    /** Statuses that block a room from being booked */
    public const ACTIVE = ['pending', 'confirmed', 'checked_in'];
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled'],
        'checked_in' => ['checked_out'],
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 20, unique: true)] private string $reference = '';
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private ?User $guest = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private ?Room $room = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $checkIn;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $checkOut;
    #[ORM\Column(type: 'smallint')] private int $guests = 1;
    #[ORM\Column] private int $total = 0;
    #[ORM\Column(options: ['default' => 0])] private int $extendedHours = 0;
    #[ORM\Column(length: 20)] private string $status = 'pending';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    #[ORM\Column(length: 80, nullable: true)] private ?string $guestIdNumber = null;
    #[ORM\Column(length: 20, nullable: true)] private ?string $guestIdType = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $actualCheckIn = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $actualCheckOut = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reference = 'MA-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getGuest(): ?User { return $this->guest; }
    public function setGuest(User $v): static { $this->guest = $v; return $this; }
    public function getRoom(): ?Room { return $this->room; }
    public function setRoom(Room $v): static { $this->room = $v; return $this; }
    public function getCheckIn(): \DateTimeImmutable { return $this->checkIn; }
    public function setCheckIn(\DateTimeImmutable $v): static { $this->checkIn = $v; return $this; }
    public function getCheckOut(): \DateTimeImmutable { return $this->checkOut; }
    public function setCheckOut(\DateTimeImmutable $v): static { $this->checkOut = $v; return $this; }
    public function getGuests(): int { return $this->guests; }
    public function setGuests(int $v): static { $this->guests = $v; return $this; }
    public function getTotal(): int { return $this->total; }
    public function setTotal(int $v): static { $this->total = $v; return $this; }
    public function getExtendedHours(): int { return $this->extendedHours; }
    public function setExtendedHours(int $v): static { $this->extendedHours = max(0, $v); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getGuestIdNumber(): ?string { return $this->guestIdNumber; }
    public function setGuestIdNumber(?string $v): static { $this->guestIdNumber = $v; return $this; }
    public function getGuestIdType(): ?string { return $this->guestIdType; }
    public function setGuestIdType(?string $v): static { $this->guestIdType = $v; return $this; }
    public function getActualCheckIn(): ?\DateTimeImmutable { return $this->actualCheckIn; }
    public function setActualCheckIn(?\DateTimeImmutable $v): static { $this->actualCheckIn = $v; return $this; }
    public function getActualCheckOut(): ?\DateTimeImmutable { return $this->actualCheckOut; }
    public function setActualCheckOut(?\DateTimeImmutable $v): static { $this->actualCheckOut = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getNights(): int { return max(0, (int) $this->checkIn->setTime(0, 0)->diff($this->checkOut->setTime(0, 0))->format('%r%a')); }
    public function getDurationHours(): int { return max(0, (int) floor(($this->checkOut->getTimestamp() - $this->checkIn->getTimestamp()) / 3600)); }
    public function getNextStatuses(): array { return self::TRANSITIONS[$this->status] ?? []; }
    public function isCancellable(): bool { return in_array($this->status, ['pending', 'confirmed'], true); }
}
