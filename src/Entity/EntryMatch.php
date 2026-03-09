<?php

namespace App\Entity;

use App\Repository\EntryMatchRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryMatchRepository::class)]
#[ORM\Table(name: 'entry_match')]
class EntryMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'entryMatch')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entry $entry = null;

    #[ORM\Column(length: 180)]
    private ?string $homeName = null;

    #[ORM\Column(length: 180)]
    private ?string $awayName = null;

    #[ORM\Column]
    private ?int $homeScore = null;

    #[ORM\Column]
    private ?int $awayScore = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $homeUser = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $awayUser = null;

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

        if (null !== $entry && $entry->getEntryMatch() !== $this) {
            $entry->setEntryMatch($this);
        }

        return $this;
    }

    public function getHomeName(): ?string
    {
        return $this->homeName;
    }

    public function setHomeName(string $homeName): static
    {
        $this->homeName = $homeName;

        return $this;
    }

    public function getAwayName(): ?string
    {
        return $this->awayName;
    }

    public function setAwayName(string $awayName): static
    {
        $this->awayName = $awayName;

        return $this;
    }

    public function getHomeScore(): ?int
    {
        return $this->homeScore;
    }

    public function setHomeScore(int $homeScore): static
    {
        $this->homeScore = $homeScore;

        return $this;
    }

    public function getAwayScore(): ?int
    {
        return $this->awayScore;
    }

    public function setAwayScore(int $awayScore): static
    {
        $this->awayScore = $awayScore;

        return $this;
    }

    public function getHomeUser(): ?User
    {
        return $this->homeUser;
    }

    public function setHomeUser(?User $homeUser): static
    {
        $this->homeUser = $homeUser;

        return $this;
    }

    public function getAwayUser(): ?User
    {
        return $this->awayUser;
    }

    public function setAwayUser(?User $awayUser): static
    {
        $this->awayUser = $awayUser;

        return $this;
    }
}
