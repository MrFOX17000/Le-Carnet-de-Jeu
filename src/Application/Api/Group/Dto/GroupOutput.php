<?php

namespace App\Application\Api\Group\Dto;

final class GroupOutput
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $createdAt,
        public readonly ?string $role,
    ) {
    }

    /**
     * @return array{id:int,name:string,createdAt:string,role:?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'createdAt' => $this->createdAt,
            'role' => $this->role,
        ];
    }
}
