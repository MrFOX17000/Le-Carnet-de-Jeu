<?php

namespace App\Entity;

use App\Repository\GroupMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Domain\Group\GroupRole;

#[ORM\Entity(repositoryClass: GroupMemberRepository::class)]
#[ORM\Table(name: 'group_member')]
#[ORM\UniqueConstraint(name: 'uniq_group_member_group_user', columns: ['group_id', 'user_id'])]
class GroupMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'groupMembers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameGroup $group = null;

    #[ORM\ManyToOne(inversedBy: 'groupMembers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(enumType: GroupRole::class)]
    private GroupRole $role;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct(GroupRole $role)
    {
        $this->role = $role;
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }
}
