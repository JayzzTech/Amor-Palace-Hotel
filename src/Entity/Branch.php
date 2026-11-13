<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Branch
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 120)] private string $name = '';
    #[ORM\Column(length: 80)] private string $city = '';
    #[ORM\Column(length: 255)] private string $address = '';
    #[ORM\Column(length: 40)] private string $phone = '';
    #[ORM\Column(length: 160)] private string $tagline = '';
    #[ORM\Column(type: 'text')] private string $description = '';
    #[ORM\Column(options: ['default' => true])] private bool $active = true;
    /** @var Collection<int, Room> */
    #[ORM\OneToMany(mappedBy: 'branch', targetEntity: Room::class)]
    private Collection $rooms;

    public function __construct() { $this->rooms = new ArrayCollection(); }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $v): static { $this->city = $v; return $this; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $v): static { $this->address = $v; return $this; }
    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $v): static { $this->phone = $v; return $this; }
    public function getTagline(): string { return $this->tagline; }
    public function setTagline(string $v): static { $this->tagline = $v; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): static { $this->description = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function getRooms(): Collection { return $this->rooms; }
}
