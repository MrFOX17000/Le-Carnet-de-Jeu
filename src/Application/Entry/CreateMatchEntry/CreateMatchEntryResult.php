<?php

namespace App\Application\Entry\CreateMatchEntry;

class CreateMatchEntryResult
{
    public function __construct(
        public int $entryId,
        public int $sessionId,
    ) {
    }
}
