<?php
// src/Controller/RegistrationController.php
namespace App\Controller;

use App\Entity\Registration;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface; // Правильный импорт
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/authentication/registration', name: 'app_registration', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ): Response {
        $user = new Registration();
        $user->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    // Хешируем пароль
                    $user->setPassword(
                        $userPasswordHasher->hashPassword(
                            $user,
                            $form->get('password')->getData()
                        )
                    );

                    // Сохраняем пользователя в базе данных
                    $entityManager->persist($user);
                    $entityManager->flush();

                    // Автоматическая авторизация пользователя
                    $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                    $tokenStorage->setToken($token);
                    $session->set('_security_main', serialize($token));

                    // Генерируем событие логина
                    $loginEvent = new InteractiveLoginEvent($request, $token);
                    $eventDispatcher->dispatch($loginEvent);

                    $this->addFlash('success', 'Регистрация прошла успешно! Добро пожаловать, ' . $user->getEmail());
                    return $this->redirectToRoute('app_home');
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('error', 'Этот email уже зарегистрирован');
                }
            } else {
                // Добавляем flash-сообщения для ошибок формы
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('authentication/registration.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}