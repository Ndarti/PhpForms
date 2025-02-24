<?php
// src/Entity/Like.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'likes')] // Изменено с 'like' на 'likes'
#[ORM\UniqueConstraint(name: 'unique_user_template', columns: ['user_id', 'template_id'])]
class Like
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private ?Registration $user = null;

    #[ORM\ManyToOne(targetEntity: Template::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(name: 'template_id', nullable: false)]
    private ?Template $template = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Registration
    {
        return $this->user;
    }

    public function setUser(?Registration $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): self
    {
        $this->template = $template;
        return $this;
    }
}