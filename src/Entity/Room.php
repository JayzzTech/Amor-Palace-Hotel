<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_branch_number', columns: ['branch_id', 'number'])]
class Room
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'rooms'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Branch $branch = null;
    #[ORM\Column(length: 10)] private string $number = '';
    #[ORM\Column(length: 80)] private string $type = '';
    #[ORM\Column(type: 'smallint')] private int $capacity = 2;
    #[ORM\Column] private int $pricePerNight = 0;
    #[ORM\Column(length: 255)] private string $amenities = '';
    #[ORM\Column(type: 'text')] private string $description = '';
    #[ORM\Column] private bool $active = true;
    #[ORM\Column(length: 20, options: ['default' => 'clean'])] private string $housekeepingStatus = 'clean';
    #[ORM\Column(length: 500, nullable: true)] private ?string $housekeepingNotes = null;
    private bool $activeBooking = false;

    public function getId(): ?int { return $this->id; }
    public function getBranch(): ?Branch { return $this->branch; }
    public function setBranch(Branch $v): static { $this->branch = $v; return $this; }
    public function getNumber(): string { return $this->number; }
    public function setNumber(string $v): static { $this->number = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): static { $this->type = $v; return $this; }
    public function getCapacity(): int { return $this->capacity; }
    public function setCapacity(int $v): static { $this->capacity = $v; return $this; }
    public function getPricePerNight(): int { return $this->pricePerNight; }
    public function setPricePerNight(int $v): static { $this->pricePerNight = $v; return $this; }
    public function getAmenities(): string { return $this->amenities; }
    public function setAmenities(string $v): static { $this->amenities = $v; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): static { $this->description = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function getHousekeepingStatus(): string { return $this->housekeepingStatus; }
    public function setHousekeepingStatus(string $v): static { $this->housekeepingStatus = $v; return $this; }
    public function getHousekeepingNotes(): ?string { return $this->housekeepingNotes; }
    public function setHousekeepingNotes(?string $v): static { $this->housekeepingNotes = $v; return $this; }
    public function hasActiveBooking(): bool { return $this->activeBooking; }
    public function setActiveBooking(bool $v): static { $this->activeBooking = $v; return $this; }
}
