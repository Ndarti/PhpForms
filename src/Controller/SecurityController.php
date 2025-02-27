<?php
// src/Controller/SecurityController.php
namespace App\Controller;

use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface; // Добавлен правильный импорт
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SecurityController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile'], []);
    }
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ): Response {
        $client = $clientRegistry->getClient('google');
        try {
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUser();
            $logger->info('Google User Class: ' . get_class($googleUser));
            $logger->info('Google User Data: ' . json_encode($googleUser));

            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $googleUser->getEmail()]);

            if ($existingUser) {
                $user = $existingUser;
                $this->addFlash('success', 'Вы успешно вошли через Google!');
            } else {
                $user = new User();
                $user->setEmail($googleUser->getEmail());
                $user->setName($googleUser->getName() ?? 'User');
                $user->setCreatedAt(new \DateTimeImmutable());
                $randomPassword = bin2hex(random_bytes(16));
                $user->setPassword($userPasswordHasher->hashPassword($user, $randomPassword));
                $entityManager->persist($user);
                $entityManager->flush();
                $this->addFlash('success', 'Регистрация через Google прошла успешно!');
            }

            // Автоматическая авторизация пользователя
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);
            $session->set('_security_main', serialize($token));

            // Генерируем событие логина
            $loginEvent = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($loginEvent);

            return $this->redirectToRoute('app_home');
        } catch (\Exception $e) {
            $logger->error('Google OAuth Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->addFlash('error', 'Ошибка при входе через Google: ' . $e->getMessage());
            return $this->redirectToRoute('app_registration');
        }
    }

    #[Route('/authentication/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticationUtils $authenticationUtils
    ): Response {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            if (!$email || !$password) {
                $error = 'Пожалуйста, заполните все поля.';
            } else {
                $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($user) {
                    if ($passwordHasher->isPasswordValid($user, $password)) {
                        return $this->redirectToRoute('app_home');
                    } else {
                        $error = 'Неверный пароль.';
                    }
                } else {
                    $error = 'Пользователь с таким email не найден.';
                }
            }
            $lastUsername = $email;
        }

        return $this->render('authentication/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
}