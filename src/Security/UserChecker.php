<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\LockedException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_BLOCKED', $roles)) {
            throw new LockedException('Пользователь заблокирован.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Нет необходимости в дополнительных проверках после авторизации
    }}