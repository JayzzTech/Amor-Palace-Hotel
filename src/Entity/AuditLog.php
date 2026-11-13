<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AuditLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?User $actor = null;
    #[ORM\Column(length: 80)] private string $action = '';
    #[ORM\Column(length: 120, nullable: true)] private ?string $subject = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $details = null;
    #[ORM\Column(length: 45)] private string $ipAddress = '';
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getActor(): ?User { return $this->actor; }
    public function setActor(?User $v): static { $this->actor = $v; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): static { $this->subject = $v; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $v): static { $this->details = $v; return $this; }
    public function getIpAddress(): string { return $this->ipAddress; }
    public function setIpAddress(string $v): static { $this->ipAddress = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
