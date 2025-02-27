<?php

namespace App\Entity;

use App\Repository\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Этот email уже зарегистрирован')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Имя не может быть пустым')]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: false)]
    #[Assert\NotBlank(message: 'Email не может быть пустым')]
    #[Assert\Email(message: 'Некорректный формат email')]
    private string $email;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $password;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $roles = ['ROLE_USER']; // По умолчанию все пользователи имеют ROLE_USER

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = ['ROLE_USER']; // Устанавливаем базовую роль по умолчанию
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getRoles(): array
    {
        $roles = $this->roles ?? ['ROLE_USER'];
        return array_unique($roles);
    }

    public function setRoles(?array $roles): self
    {
        // Убеждаемся, что роли всегда содержат только одну роль: ROLE_ADMIN, ROLE_USER или ROLE_BLOCKED
        if (empty($roles)) {
            $this->roles = ['ROLE_USER'];
        } elseif (in_array('ROLE_ADMIN', $roles)) {
            $this->roles = ['ROLE_ADMIN'];
        } elseif (in_array('ROLE_BLOCKED', $roles)) {
            $this->roles = ['ROLE_BLOCKED'];
        } else {
            $this->roles = ['ROLE_USER'];
        }
        return $this;
    }

    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return $this->email; }
    public function getSalt(): ?string { return null; }

    // Метод для проверки, является ли пользователь администратором
    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles());
    }
}