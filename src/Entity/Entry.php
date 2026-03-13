<?php

namespace App\Entity;

use App\Domain\Entry\EntryType;
use App\Repository\EntryRepository;
use App\Entity\EntryMatch;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryRepository::class)]
#[ORM\Table(name: 'entry')]
class Entry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameGroup $group = null;

    #[ORM\Column(enumType: EntryType::class)]
    private EntryType $type;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\OneToOne(mappedBy: 'entry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EntryMatch $entryMatch = null;

    /**
     * @var Collection<int, EntryScore>
     */
    #[ORM\OneToMany(targetEntity: EntryScore::class, mappedBy: 'entry', orphanRemoval: true, cascade: ['persist'])]
    private Collection $scores;

    public function __construct(EntryType $type)
    {
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
        $this->scores = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        if (null === $session) {
            if (null !== $this->session) {
                $currentSession = $this->session;
                $this->session = null;

                if ($currentSession->getEntries()->contains($this)) {
                    $currentSession->removeEntry($this);
                }
            }

            return $this;
        }

        $this->session = $session;

        if (!$session->getEntries()->contains($this)) {
            $session->addEntry($this);
        }

        return $this;
    }

    public function getGroup(): ?GameGroup
    {
        return $this->group;
    }

    public function setGroup(?GameGroup $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getType(): EntryType
    {
        return $this->type;
    }

    public function setType(EntryType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getEntryMatch(): ?EntryMatch
    {
        return $this->entryMatch;
    }

    public function setEntryMatch(?EntryMatch $entryMatch): static
    {
        // Keep both sides synchronized for MATCH details.
        if (null === $entryMatch) {
            if (null !== $this->entryMatch) {
                $this->entryMatch->setEntry(null);
            }

            $this->entryMatch = null;

            return $this;
        }

        $this->entryMatch = $entryMatch;

        if ($entryMatch->getEntry() !== $this) {
            $entryMatch->setEntry($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, EntryScore>
     */
    public function getScores(): Collection
    {
        return $this->scores;
    }

    public function addScore(EntryScore $score): static
    {
        if (!$this->scores->contains($score)) {
            $this->scores->add($score);
            $score->setEntry($this);
        }

        return $this;
    }

    public function removeScore(EntryScore $score): static
    {
        if ($this->scores->removeElement($score)) {
            if ($score->getEntry() === $this) {
                $score->setEntry(null);
            }
        }

        return $this;
    }
}
