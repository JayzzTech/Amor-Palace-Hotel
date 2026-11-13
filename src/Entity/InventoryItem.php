<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InventoryItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Branch $branch = null;
    #[ORM\Column(length: 120)] private string $name = '';
    #[ORM\Column(length: 30)] private string $category = 'supplies';
    #[ORM\Column] private int $quantity = 0;
    #[ORM\Column] private int $minimumQuantity = 0;
    #[ORM\Column(length: 20)] private string $unit = 'pcs';
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getBranch(): ?Branch { return $this->branch; }
    public function setBranch(Branch $v): static { $this->branch = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $v): static { $this->category = $v; return $this; }
    public function getQuantity(): int { return max(0, $this->quantity); }
    public function setQuantity(int $v): static { $this->quantity = max(0, $v); return $this; }
    public function getMinimumQuantity(): int { return max(0, $this->minimumQuantity); }
    public function setMinimumQuantity(int $v): static { $this->minimumQuantity = max(0, $v); return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $v): static { $this->unit = $v; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
