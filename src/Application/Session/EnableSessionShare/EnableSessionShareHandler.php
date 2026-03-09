<?php

namespace App\Application\Session\EnableSessionShare;

use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class EnableSessionShareHandler
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function handle(EnableSessionShareCommand $command): EnableSessionShareResult
    {
        // Vérifier que la session existe
        $session = $this->sessionRepository->find($command->getSessionId());
        if (null === $session) {
            throw new \InvalidArgumentException(
                sprintf('Session with ID %d not found.', $command->getSessionId())
            );
        }

        // RÈGLE CRITIQUE : vérifier que la session appartient au groupe
        if ($session->getGroup()->getId() !== $command->getGroupId()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Session %d does not belong to group %d.',
                    $command->getSessionId(),
                    $command->getGroupId()
                )
            );
        }

        // Réutiliser le token existant ou en générer un nouveau
        $token = $session->getUnlistedToken();
        if (null === $token) {
            $token = $this->generateToken();
            $session->setUnlistedToken($token);
        }

        // Persister et flush
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return new EnableSessionShareResult(
            sessionId: $session->getId() ?? 0,
            unlistedToken: $token,
        );
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
