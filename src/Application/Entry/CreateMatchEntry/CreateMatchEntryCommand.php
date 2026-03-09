<?php

namespace App\Application\Entry\CreateMatchEntry;

class CreateMatchEntryCommand
{
    public function __construct(
        public int $groupId,
        public int $sessionId,
        public int $creatorUserId,
        public string $homeName,
        public string $awayName,
        public int $homeScore,
        public int $awayScore,
        public ?string $label = null,
        public ?int $homeUserId = null,
        public ?int $awayUserId = null,
    ) {
    }
}
