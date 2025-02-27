<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
#[ORM\Entity]
class FormSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Template::class, inversedBy: 'formSubmissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Template $template = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Question $question = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'text')]
    private string $answer;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $submittedAt;

    public function getId(): ?int { return $this->id; }

    public function getTemplate(): ?Template { return $this->template; }
    public function setTemplate(?Template $template): self { $this->template = $template; return $this; }

    public function getQuestion(): ?Question { return $this->question; }
    public function setQuestion(?Question $question): self { $this->question = $question; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getAnswer(): string { return $this->answer; }
    public function setAnswer(string $answer): self { $this->answer = $answer; return $this; }

    public function getSubmittedAt(): \DateTimeInterface { return $this->submittedAt; }
    public function setSubmittedAt(\DateTimeInterface $submittedAt): self { $this->submittedAt = $submittedAt; return $this; }
}