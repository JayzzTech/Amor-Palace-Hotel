<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontendController extends AppController
{
    #[Route('/', name: 'frontend_home', methods: ['GET'], priority: 100)]
    #[Route('/hotels', name: 'frontend_hotels', methods: ['GET'], priority: 100)]
    #[Route('/about', name: 'frontend_about', methods: ['GET'], priority: 100)]
    #[Route('/contact', name: 'frontend_contact', methods: ['GET'], priority: 100)]
    #[Route('/branches/{id}', name: 'frontend_branch', requirements: ['id' => '\\d+'], methods: ['GET'], priority: 100)]
    #[Route('/book/{id}', name: 'frontend_book', requirements: ['id' => '\\d+'], methods: ['GET'], priority: 100)]
    #[Route('/book/', name: 'frontend_book_index', methods: ['GET'], priority: 100)]
    #[Route('/payment/{id}', name: 'frontend_payment', requirements: ['id' => '\\d+'], methods: ['GET'], priority: 100)]
    #[Route('/login', name: 'frontend_login', methods: ['GET'], priority: 100)]
    #[Route('/forgot-password', name: 'frontend_forgot_password', methods: ['GET'], priority: 100)]
    #[Route('/reset-password', name: 'frontend_reset_password', methods: ['GET'], priority: 100)]
    #[Route('/privacy', name: 'frontend_privacy', methods: ['GET'], priority: 100)]
    #[Route('/terms', name: 'frontend_terms', methods: ['GET'], priority: 100)]
    #[Route('/cancellation-policy', name: 'frontend_cancellation_policy', methods: ['GET'], priority: 100)]
    #[Route('/register', name: 'frontend_register', methods: ['GET'], priority: 100)]
    #[Route('/account', name: 'frontend_account', methods: ['GET'], priority: 100)]
    #[Route('/staff', name: 'frontend_staff', methods: ['GET'], priority: 100)]
    #[Route('/super', name: 'frontend_super', methods: ['GET'], priority: 100)]
    #[Route('/access-denied', name: 'frontend_access_denied', methods: ['GET'], priority: 100)]
    public function app(): Response
    {
        $path = dirname(__DIR__, 2).'/public/react/index.html';
        if (!is_file($path)) {
            return new Response('React frontend has not been built. Run npm run build in frontend/.', 503);
        }

        return new Response((string) file_get_contents($path), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
