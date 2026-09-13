<?php

namespace App\Domain\Grading\Enums;

enum ScaleTypeEnum: string
{
    case NUMERIC = 'NUMERIC';
    case QUALITATIVE = 'QUALITATIVE';
    case LETTER = 'LETTER';
}
