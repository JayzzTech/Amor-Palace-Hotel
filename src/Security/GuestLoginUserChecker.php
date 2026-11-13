<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class GuestLoginUserChecker implements UserCheckerInterface
{
    public function __construct(private GuestLoginAttemptGuard $guard)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $lockedUntil = $this->guard->lockoutUntil($user);
        if ($lockedUntil) {
            throw new CustomUserMessageAccountStatusException('Too many failed sign-in attempts. Please try again in 15 minutes.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
