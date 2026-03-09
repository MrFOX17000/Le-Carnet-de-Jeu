<?php

namespace App\Application\Invite\AcceptInvite;

use App\Entity\GroupMember;
use App\Repository\InviteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AcceptInviteHandler
{
    public function __construct(
        private readonly InviteRepository $inviteRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function handle(AcceptInviteCommand $command): AcceptInviteResult
    {
        // Trouver l'invitation par token
        $invite = $this->inviteRepository->findOneBy(['token' => $command->getToken()]);
        if (null === $invite) {
            throw new \InvalidArgumentException('Invitation not found or token is invalid.');
        }

        // Vérifier que l'invitation n'est pas expirée
        if ($invite->getExpiresAt() < new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Invitation has expired.');
        }

        // Vérifier que l'invitation n'a pas déjà été acceptée
        if (null !== $invite->getAcceptedAt()) {
            throw new \InvalidArgumentException('Invitation has already been accepted.');
        }

        // Charger l'utilisateur
        $user = $this->userRepository->find($command->getUserId());
        if (null === $user) {
            throw new \InvalidArgumentException(
                sprintf('User with ID %d not found.', $command->getUserId())
            );
        }

        $group = $invite->getGroup();

        // Vérifier que l'utilisateur n'est pas déjà membre du groupe
        // Forcer le chargement des relations
        $this->entityManager->refresh($group);
        
        foreach ($group->getGroupMembers() as $membership) {
            if ($membership->getUser()?->getId() === $user->getId()) {
                throw new \InvalidArgumentException('User is already a member of this group.');
            }
        }

        // Créer le GroupMember avec le rôle de l'invitation
        $membership = new GroupMember($invite->getRole());
        $membership->setUser($user);
        $membership->setGroup($group);

        // Synchroniser les relations bidirectionnelles
        $group->addGroupMember($membership);
        $user->addGroupMember($membership);

        // Marquer l'invitation comme acceptée
        $invite->setAcceptedAt(new \DateTimeImmutable());

        // Persister et flush
        $this->entityManager->persist($membership);
        $this->entityManager->persist($invite);
        $this->entityManager->flush();

        $memberId = $membership->getId();
        if (null === $memberId) {
            throw new \LogicException('Member ID should not be null after flush.');
        }

        return new AcceptInviteResult(
            groupId: $group->getId(),
            memberId: $memberId,
        );
    }
}
