<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

abstract class AppController extends AbstractController
{
    /** All POST forms share one CSRF token id: "app". */
    protected function csrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('app', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token. Please go back and try again.');
        }
    }

    protected function me(): User
    {
        $u = $this->getUser();
        \assert($u instanceof User);

        return $u;
    }
}
