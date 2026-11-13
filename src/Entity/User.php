<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLES = ['ROLE_USER' => 'Guest', 'ROLE_STAFF' => 'Staff', 'ROLE_ADMIN' => 'Branch admin', 'ROLE_SUPER_ADMIN' => 'Super admin'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 180, unique: true)] private string $email = '';
    #[ORM\Column(length: 120)] private string $fullName = '';
    #[ORM\Column(length: 40, nullable: true)] private ?string $phone = null;
    #[ORM\Column(type: 'json')] private array $roles = [];
    #[ORM\Column] private string $password = '';
    #[ORM\Column(length: 64, nullable: true)] private ?string $resetToken = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $resetTokenExpiresAt = null;
    #[ORM\Column] private bool $emailVerified = false;
    #[ORM\Column(length: 64, nullable: true)] private ?string $emailVerificationToken = null;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')] private ?Branch $branch = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column(options: ['default' => 0])] private int $failedLoginAttempts = 0;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $loginLockedUntil = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): static { $this->email = mb_strtolower(trim($v)); return $this; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $v): static { $this->fullName = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function setRoles(array $v): static { $this->roles = $v; return $this; }
    public function getPrimaryRole(): string
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_STAFF'] as $r) {
            if (in_array($r, $this->roles, true)) return $r;
        }
        return 'ROLE_USER';
    }
    public function getPrimaryRoleLabel(): string { return self::ROLES[$this->getPrimaryRole()]; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $v): static { $this->password = $v; return $this; }
    public function getResetToken(): ?string { return $this->resetToken; }
    public function setResetToken(?string $v): static { $this->resetToken = $v; return $this; }
    public function getResetTokenExpiresAt(): ?\DateTimeImmutable { return $this->resetTokenExpiresAt; }
    public function setResetTokenExpiresAt(?\DateTimeImmutable $v): static { $this->resetTokenExpiresAt = $v; return $this; }
    public function isEmailVerified(): bool { return $this->emailVerified; }
    public function setEmailVerified(bool $v): static { $this->emailVerified = $v; return $this; }
    public function getEmailVerificationToken(): ?string { return $this->emailVerificationToken; }
    public function setEmailVerificationToken(?string $v): static { $this->emailVerificationToken = $v; return $this; }
    public function getBranch(): ?Branch { return $this->branch; }
    public function setBranch(?Branch $v): static { $this->branch = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getFailedLoginAttempts(): int { return $this->failedLoginAttempts; }
    public function setFailedLoginAttempts(int $value): static { $this->failedLoginAttempts = max(0, $value); return $this; }
    public function getLoginLockedUntil(): ?\DateTimeImmutable { return $this->loginLockedUntil; }
    public function setLoginLockedUntil(?\DateTimeImmutable $value): static { $this->loginLockedUntil = $value; return $this; }
    public function eraseCredentials(): void {}
}
