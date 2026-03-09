<?php

namespace App\Domain\Group;

enum GroupRole: string
{
    case OWNER = 'OWNER';
    case MEMBER = 'MEMBER';
}