<?php

namespace App\Application\Api\Session\GetSessionDetails;

use App\Application\Api\Session\Dto\EntryOutput;
use App\Application\Api\Session\Dto\SessionDetailOutput;
use App\Domain\Entry\EntryType;
use App\Entity\Entry;
use App\Repository\SessionRepository;

final class GetSessionDetailsHandler
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    public function handle(GetSessionDetailsQuery $query): ?SessionDetailOutput
    {
        $session = $this->sessionRepository->find($query->sessionId);

        if ($session === null) {
            return null;
        }

        if ($session->getGroup()?->getId() !== $query->groupId) {
            return null;
        }

        $entries = [];
        foreach ($session->getEntries() as $entry) {
            $entries[] = $this->mapEntry($entry);
        }

        return new SessionDetailOutput(
            id: (int) $session->getId(),
            groupId: (int) $session->getGroup()?->getId(),
            activityId: (int) $session->getActivity()?->getId(),
            activityName: (string) $session->getActivity()?->getName(),
            title: $session->getTitle(),
            playedAt: $session->getPlayedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            createdAt: $session->getCreatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            createdById: (int) $session->getCreatedBy()?->getId(),
            createdByEmail: (string) $session->getCreatedBy()?->getEmail(),
            entries: $entries,
        );
    }

    private function mapEntry(Entry $entry): EntryOutput
    {
        $details = [];

        if ($entry->getType() === EntryType::SCORE_SIMPLE) {
            $scores = [];
            foreach ($entry->getScores() as $score) {
                $scores[] = [
                    'participantName' => $score->getParticipantName(),
                    'score' => $score->getScore(),
                ];
            }
            $details = ['scores' => $scores];
        } elseif ($entry->getType() === EntryType::MATCH) {
            $match = $entry->getEntryMatch();
            if ($match !== null) {
                $details = [
                    'homeName' => $match->getHomeName(),
                    'awayName' => $match->getAwayName(),
                    'homeScore' => $match->getHomeScore(),
                    'awayScore' => $match->getAwayScore(),
                ];
            }
        }

        return new EntryOutput(
            id: (int) $entry->getId(),
            type: $entry->getType()->value,
            label: $entry->getLabel(),
            details: $details,
        );
    }
}
