<?php

namespace App\Application\Entry\CreateEntry;

use App\Domain\Entry\EntryType;

class CreateEntryCommand
{
    /**
     * @param array<array{participantName: string, score: float, userId?: int|null}> $scores
     */
    public function __construct(
        public int $sessionId,
        public int $groupId,
        public int $creatorUserId,
        public EntryType $type,
        public ?string $label,
        public array $scores,
    ) {
    }
}
