<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'template')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
class Template
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'public'])]
    private string $status = 'public';

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $tags = [];

    #[ORM\OneToMany(mappedBy: 'template', targetEntity: FormSubmission::class)]
    private Collection $formSubmissions;

    #[ORM\OneToMany(mappedBy: 'template', targetEntity: Comment::class, cascade: ['remove'])]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'template', targetEntity: Like::class)]
    private Collection $likes;

    #[ORM\OneToMany(mappedBy: 'template', targetEntity: Question::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $questions;
    public function __construct()
    {
        $this->formSubmissions = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->questions = new ArrayCollection(); // Инициализация коллекции вопросов
        $this->createdAt = new \DateTime();
    }

    // Геттеры и сеттеры для существующих полей
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self { $this->author = $author; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getTags(): array { return $this->tags; }
    public function setTags(array $tags): self { $this->tags = $tags; return $this; }
    public function getFormSubmissions(): Collection { return $this->formSubmissions; }
    public function getComments(): Collection { return $this->comments; }
    public function getLikes(): Collection { return $this->likes; }

    // Новые геттеры и сеттеры для questions
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions[] = $question;
            $question->setTemplate($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): self
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getTemplate() === $this) {
                $question->setTemplate(null);
            }
        }
        return $this;
    }

}