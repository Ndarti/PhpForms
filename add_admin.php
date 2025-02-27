<?php

require 'vendor/autoload.php';

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Загрузка контейнера Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Получение EntityManager и PasswordHasher
$entityManager = $container->get('doctrine.orm.entity_manager');
$passwordHasher = $container->get('security.user_password_hasher');

// Создание пользователя
$user = new User();
$user->setEmail('admin@example.com');
$user->setRoles(['ROLE_ADMIN']);
$hashedPassword = $passwordHasher->hashPassword($user, '1');
$user->setPassword($hashedPassword);

// Сохранение в базу данных
$entityManager->persist($user);
$entityManager->flush();

echo "Admin user created successfully!\n";
