<?php

namespace App\Controller;

use App\Entity\Branch;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/super')]
class SuperAdminController extends AppController
{
    #[Route('', name: 'app_super')]
    public function dashboard(EntityManagerInterface $em, ReservationRepository $res): Response
    {
        $branches = $em->getRepository(Branch::class)->findBy([], ['name' => 'ASC']);
        $rows = array_map(fn (Branch $b) => [
            'branch' => $b, 'rooms' => count($b->getRooms()),
            'inhouse' => $res->countFor($b, ['checked_in']), 'pending' => $res->countFor($b, ['pending']),
            'revenue' => $res->revenueFor($b),
        ], $branches);

        return $this->render('super/dashboard.html.twig', [
            'rows' => $rows, 'branches' => $branches,
            'users' => $em->getRepository(User::class)->findBy([], ['id' => 'DESC']),
            'recent' => $res->forBranch(null, null, 12),
            'totals' => [
                'revenue' => $res->revenueFor(null),
                'reservations' => $res->countFor(null, \App\Entity\Reservation::STATUSES),
                'pending' => $res->countFor(null, ['pending']),
                'users' => $em->getRepository(User::class)->count([]),
            ],
            'roles' => User::ROLES,
        ]);
    }

    #[Route('/branch/save/{id}', name: 'app_super_branch_save', defaults: ['id' => null], methods: ['POST'])]
    public function saveBranch(?Branch $branch, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        $p = $request->request;
        $name = trim((string) $p->get('name'));
        $city = trim((string) $p->get('city'));
        if ($name === '' || $city === '') {
            $this->addFlash('error', 'A branch needs at least a name and a city.');

            return $this->redirectToRoute('app_super');
        }
        $branch ??= new Branch();
        $branch->setName($name)->setCity($city)->setAddress(trim((string) $p->get('address')))
            ->setPhone(trim((string) $p->get('phone')))->setTagline(trim((string) $p->get('tagline')))
            ->setDescription(trim((string) $p->get('description')));
        $em->persist($branch);
        $em->flush();
        $this->addFlash('success', sprintf('Branch "%s" was saved.', $name));

        return $this->redirectToRoute('app_super');
    }

    #[Route('/user/save/{id}', name: 'app_super_user_save', defaults: ['id' => null], methods: ['POST'])]
    public function saveUser(?User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->csrf($request);
        $p = $request->request;
        $role = (string) $p->get('role');
        if (!isset(User::ROLES[$role])) {
            $this->addFlash('error', 'Unknown role.');

            return $this->redirectToRoute('app_super');
        }
        $branch = $p->get('branch') ? $em->getRepository(Branch::class)->find((int) $p->get('branch')) : null;
        if ($role !== 'ROLE_USER' && $role !== 'ROLE_SUPER_ADMIN' && (!$branch || !$branch->isActive())) {
            $this->addFlash('error', 'Staff and branch admins must be assigned to an active branch.');

            return $this->redirectToRoute('app_super');
        }
        if ($user) {
            if ($user === $this->me() && $role !== 'ROLE_SUPER_ADMIN') {
                $this->addFlash('error', 'You cannot remove your own super admin access.');

                return $this->redirectToRoute('app_super');
            }
        } else {
            $email = trim((string) $p->get('email'));
            $pw = (string) $p->get('password');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8 || trim((string) $p->get('name')) === '') {
                $this->addFlash('error', 'New users need a name, a valid email and a password of 8+ characters.');

                return $this->redirectToRoute('app_super');
            }
            if ($em->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)])) {
                $this->addFlash('error', 'That email is already registered.');

                return $this->redirectToRoute('app_super');
            }
            $user = (new User())->setEmail($email)->setFullName(trim((string) $p->get('name')));
            $user->setPassword($hasher->hashPassword($user, $pw));
            $em->persist($user);
        }
        $user->setRoles($role === 'ROLE_USER' ? [] : [$role])->setBranch($role === 'ROLE_SUPER_ADMIN' ? null : $branch);
        $em->flush();
        $this->addFlash('success', sprintf('%s is now %s.', $user->getFullName(), strtolower($user->getPrimaryRoleLabel())));

        return $this->redirectToRoute('app_super');
    }
}
