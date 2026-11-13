<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class GuestLoginAttemptGuard
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function lockoutUntil(User $user): ?\DateTimeImmutable
    {
        if ($user->getPrimaryRole() !== 'ROLE_USER') {
            return null;
        }

        $lockedUntil = $user->getLoginLockedUntil();
        if ($lockedUntil && $lockedUntil <= new \DateTimeImmutable()) {
            $user->setFailedLoginAttempts(0)->setLoginLockedUntil(null);
            $this->entityManager->flush();

            return null;
        }

        return $lockedUntil;
    }

    public function recordFailure(User $user): void
    {
        if ($user->getPrimaryRole() !== 'ROLE_USER' || $this->lockoutUntil($user)) {
            return;
        }

        $attempts = $user->getFailedLoginAttempts() + 1;
        $user->setFailedLoginAttempts($attempts);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $user->setLoginLockedUntil(new \DateTimeImmutable(sprintf('+%d minutes', self::LOCKOUT_MINUTES)));
        }
        $this->entityManager->flush();
    }

    public function recordSuccess(User $user): void
    {
        if ($user->getPrimaryRole() !== 'ROLE_USER') {
            return;
        }

        if ($user->getFailedLoginAttempts() > 0 || $user->getLoginLockedUntil() !== null) {
            $user->setFailedLoginAttempts(0)->setLoginLockedUntil(null);
            $this->entityManager->flush();
        }
    }
}
