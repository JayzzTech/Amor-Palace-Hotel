<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MaintenanceRequest
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Room $room = null;
    #[ORM\Column(length: 120)] private string $title = '';
    #[ORM\Column(type: 'text')] private string $details = '';
    #[ORM\Column(length: 20)] private string $priority = 'normal';
    #[ORM\Column(length: 20)] private string $status = 'open';
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getRoom(): ?Room { return $this->room; }
    public function setRoom(Room $v): static { $this->room = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getDetails(): string { return $this->details; }
    public function setDetails(string $v): static { $this->details = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
