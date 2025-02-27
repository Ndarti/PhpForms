<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(RegistrationRepository $registrationRepository): Response
    {
        $users = $registrationRepository->findAll();
        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/{id}/toggle-block', name: 'admin_toggle_block', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleBlock(int $id, RegistrationRepository $registrationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $registrationRepository->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
        }

        // Переключаем роль между ROLE_USER и ROLE_BLOCKED
        $currentRoles = $user->getRoles();
        if (in_array('ROLE_BLOCKED', $currentRoles)) {
            $user->setRoles(['ROLE_USER']); // Разблокируем, возвращаем ROLE_USER
        } else {
            $user->setRoles(['ROLE_BLOCKED']); // Блокируем, устанавливаем ROLE_BLOCKED
        }
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'role' => $user->getRoles()[0]]);
    }
    #[Route('/admin/user/{id}/delete', name: 'admin_delete_user', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUser(int $id, RegistrationRepository $registrationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $registrationRepository->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/admin/user/{id}/toggle-admin', name: 'admin_toggle_admin', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleAdmin(int $id, RegistrationRepository $registrationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $registrationRepository->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Пользователь не найден'], 404);
        }

        // Переключаем роль между ROLE_ADMIN и ROLE_USER
        if ($user->isAdmin()) {
            $user->setRoles(['ROLE_USER']);
        } else {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $entityManager->flush();

        // Если администратор лишает себя роли, перенаправляем на главную страницу
        if ($user->getId() === $this->getUser()->getId() && !$user->isAdmin()) {
            return new JsonResponse(['success' => true, 'redirect' => true]);
        }

        return new JsonResponse(['success' => true, 'isAdmin' => $user->isAdmin()]);
    }
}