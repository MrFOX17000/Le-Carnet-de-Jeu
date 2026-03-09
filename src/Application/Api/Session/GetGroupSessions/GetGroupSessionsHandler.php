<?php

namespace App\Application\Api\Session\GetGroupSessions;

use App\Application\Api\Session\Dto\SessionOutput;
use App\Entity\Session;
use App\Repository\SessionRepository;

final class GetGroupSessionsHandler
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    /**
     * @return list<SessionOutput>
     */
    public function handle(GetGroupSessionsQuery $query): array
    {
        $sessions = $this->sessionRepository->findBy(
            ['group' => $query->groupId],
            ['playedAt' => 'DESC']
        );

        $result = [];
        foreach ($sessions as $session) {
            $result[] = $this->mapSession($session);
        }

        return $result;
    }

    private function mapSession(Session $session): SessionOutput
    {
        return new SessionOutput(
            id: (int) $session->getId(),
            groupId: (int) $session->getGroup()?->getId(),
            activityId: (int) $session->getActivity()?->getId(),
            activityName: (string) $session->getActivity()?->getName(),
            title: $session->getTitle(),
            playedAt: $session->getPlayedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            entriesCount: $session->getEntries()->count(),
        );
    }
}
