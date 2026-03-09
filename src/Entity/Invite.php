<?php

namespace App\Entity;

use App\Domain\Group\GroupRole;
use App\Repository\InviteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InviteRepository::class)]
#[ORM\Table(name: 'invite')]
#[ORM\UniqueConstraint(name: 'uniq_invite_token', columns: ['token'])]
class Invite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invites')]
    #[ORM\JoinColumn(nullable: false)]
    private GameGroup $group;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 64)]
    private string $token;

    #[ORM\Column(enumType: GroupRole::class)]
    private GroupRole $role;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\ManyToOne(inversedBy: 'invites')]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    public function __construct(GroupRole $role = GroupRole::MEMBER)
    {
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): GameGroup
    {
        return $this->group;
    }

    public function setGroup(GameGroup $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getRole(): GroupRole
    {
        return $this->role;
    }

    public function setRole(GroupRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}