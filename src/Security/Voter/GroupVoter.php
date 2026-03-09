<?php

namespace App\Security\Voter;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class GroupVoter extends Voter
{
    public const VIEW = 'GROUP_VIEW';
    public const MANAGE = 'GROUP_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true)
            && $subject instanceof GameGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var GameGroup $group */
        $group = $subject;

        foreach ($group->getGroupMembers() as $membership) {
            if ($membership->getUser()?->getId() !== $user->getId()) {
                continue;
            }

            return match ($attribute) {
                self::VIEW => true,
                self::MANAGE => $membership->getRole() === GroupRole::OWNER,
                default => false,
            };
        }

        return false;
    }
}