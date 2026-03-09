<?php

namespace App\Domain\Entry;

enum EntryType: string
{
    case SCORE_SIMPLE = 'score_simple';
    case MATCH = 'match';
}
