<?php
namespace App\Entity;
use App\Entity\Template;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Template::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Template $template = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'boolean')]
    private bool $showInTable = true;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getTemplate(): ?Template { return $this->template; }
    public function setTemplate(?Template $template): self { $this->template = $template; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function isShowInTable(): bool { return $this->showInTable; }
    public function setShowInTable(bool $showInTable): self { $this->showInTable = $showInTable; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getOptions(): array
    {
        if ($this->type === 'checkbox' && $this->description) {
            return array_map('trim', explode(',', $this->description));
        }
        return [];
    }
}