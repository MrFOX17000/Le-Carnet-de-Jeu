<?php

namespace App\Application\Api\Session\Dto;

final class EntryOutput
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?string $label,
        public readonly array $details,
    ) {
    }
}
