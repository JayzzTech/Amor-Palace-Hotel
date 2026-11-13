<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class GuestLoginAttemptSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GuestLoginAttemptGuard $guard,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport || !$passport->hasBadge(UserBadge::class)) {
            return;
        }

        $email = mb_strtolower(trim($passport->getBadge(UserBadge::class)->getUserIdentifier()));
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            $this->guard->recordFailure($user);
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $this->guard->recordSuccess($user);
        }
    }
}
