<?php

namespace App\Entity;

use App\Repository\EntryScoreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryScoreRepository::class)]
#[ORM\Table(name: 'entry_score')]
class EntryScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'scores')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entry $entry = null;

    #[ORM\Column(length: 180)]
    private ?string $participantName = null;

    #[ORM\Column]
    private ?float $score = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(?Entry $entry): static
    {
        $this->entry = $entry;

        return $this;
    }

    public function getParticipantName(): ?string
    {
        return $this->participantName;
    }

    public function setParticipantName(string $participantName): static
    {
        $this->participantName = $participantName;

        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(float $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
