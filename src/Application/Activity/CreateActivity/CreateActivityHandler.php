<?php

namespace App\Application\Activity\CreateActivity;

use App\Entity\Activity;
use App\Repository\GameGroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CreateActivityHandler
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function handle(CreateActivityCommand $command): CreateActivityResult
    {
        // Charger le groupe
        $group = $this->groupRepository->find($command->getGroupId());
        if (null === $group) {
            throw new \InvalidArgumentException(
                sprintf('Group with ID %d not found.', $command->getGroupId())
            );
        }

        // Charger l'utilisateur créateur
        $creator = $this->userRepository->find($command->getCreatorUserId());
        if (null === $creator) {
            throw new \InvalidArgumentException(
                sprintf('User with ID %d not found.', $command->getCreatorUserId())
            );
        }

        // Valider le nom
        $name = trim($command->getName());
        if ($name === '') {
            throw new \InvalidArgumentException('Activity name cannot be empty.');
        }

        // Créer l'activité
        $activity = new Activity();
        $activity->setName($name);
        $activity->setGroup($group);
        $activity->setCreatedBy($creator);

        // Synchroniser les relations
        $group->addActivity($activity);

        // Persister et flush
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $activityId = $activity->getId();
        if (null === $activityId) {
            throw new \LogicException('Activity ID should not be null after flush.');
        }

        return new CreateActivityResult(
            activityId: $activityId,
            groupId: $group->getId(),
        );
    }
}
