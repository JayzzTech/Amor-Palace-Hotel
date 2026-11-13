<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AppController
{
    #[Route('/access-denied', name: 'app_access_denied')]
    public function accessDenied(): Response
    {
        return $this->redirectToRoute('frontend_access_denied');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) return $this->redirectToRoute('app_post_login');

        return $this->render('security/login.html.twig', [
            'last' => $auth->getLastUsername(), 'error' => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never { throw new \LogicException('Handled by the firewall.'); }

    #[Route('/welcome', name: 'app_post_login')]
    public function postLogin(): Response
    {
        return match (true) {
            $this->isGranted('ROLE_SUPER_ADMIN') => $this->redirectToRoute('app_super'),
            $this->isGranted('ROLE_STAFF') => $this->redirectToRoute('app_staff'),
            default => $this->redirectToRoute('app_account'),
        };
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->getUser()) return $this->redirectToRoute('app_account');

        $errors = [];
        $f = ['name' => '', 'email' => '', 'phone' => ''];
        if ($request->isMethod('POST')) {
            $this->csrf($request);
            $f = ['name' => trim((string) $request->request->get('name')), 'email' => trim((string) $request->request->get('email')), 'phone' => trim((string) $request->request->get('phone'))];
            $pw = (string) $request->request->get('password');
            $confirmPw = (string) $request->request->get('password_confirm');
            if ($f['name'] === '') $errors[] = 'Please enter your full name.';
            if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
            if (strlen($pw) < 8) $errors[] = 'Your password needs at least 8 characters.';
            if ($pw !== $confirmPw) $errors[] = 'Your passwords do not match.';
            if (!$request->request->getBoolean('terms')) $errors[] = 'Please accept the Terms and Privacy Policy.';
            if (!$errors && $em->getRepository(User::class)->findOneBy(['email' => mb_strtolower($f['email'])])) {
                $errors[] = 'An account with this email already exists. Try signing in.';
            }
            if (!$errors) {
                $u = (new User())->setEmail($f['email'])->setFullName($f['name'])->setPhone($f['phone'] ?: null);
                $u->setPassword($hasher->hashPassword($u, $pw));
                $em->persist($u);
                $em->flush();
                $this->addFlash('success', 'Your account is ready. Sign in to book your stay.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', ['errors' => $errors, 'f' => $f]);
    }
}
