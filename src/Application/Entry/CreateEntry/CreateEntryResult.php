<?php

namespace App\Application\Entry\CreateEntry;

class CreateEntryResult
{
    public function __construct(
        public int $entryId,
        public int $sessionId,
    ) {
    }
}
