<?php

namespace App\Application\Session\CreateSession;

use App\Entity\Session;
use App\Repository\ActivityRepository;
use App\Repository\GameGroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CreateSessionHandler
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function handle(CreateSessionCommand $command): CreateSessionResult
    {
        // Vérifier que le groupe existe
        $group = $this->groupRepository->find($command->getGroupId());
        if (null === $group) {
            throw new \InvalidArgumentException(
                sprintf('Group with ID %d not found.', $command->getGroupId())
            );
        }

        // Vérifier que l'activité existe
        $activity = $this->activityRepository->find($command->getActivityId());
        if (null === $activity) {
            throw new \InvalidArgumentException(
                sprintf('Activity with ID %d not found.', $command->getActivityId())
            );
        }

        // RÈGLE CRITIQUE : vérifier que l'activité appartient au groupe
        if ($activity->getGroup()->getId() !== $group->getId()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Activity %d does not belong to group %d.',
                    $command->getActivityId(),
                    $command->getGroupId()
                )
            );
        }

        // Vérifier que le créateur existe
        $creator = $this->userRepository->find($command->getCreatorUserId());
        if (null === $creator) {
            throw new \InvalidArgumentException(
                sprintf('User with ID %d not found.', $command->getCreatorUserId())
            );
        }

        // Vérifier que playedAt est renseigné
        if (null === $command->getPlayedAt()) {
            throw new \InvalidArgumentException('playedAt is required.');
        }

        // Créer la session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($creator);
        $session->setPlayedAt($command->getPlayedAt());

        // Normaliser et renseigner le titre (optionnel)
        $title = $command->getTitle() !== null ? trim($command->getTitle()) : null;
        if ($title === '') {
            $title = null;
        }
        $session->setTitle($title);

        // Synchroniser les relations bidirectionnelles
        $group->addSession($session);
        $activity->addSession($session);

        // Persister et flush
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $sessionId = $session->getId();
        if (null === $sessionId) {
            throw new \LogicException('Session ID should not be null after flush.');
        }

        return new CreateSessionResult(
            sessionId: $sessionId,
            groupId: $group->getId(),
        );
    }
}
