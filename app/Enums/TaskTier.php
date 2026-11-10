<?php

namespace App\Enums;

enum TaskTier: string
{
    case Must = 'must';
    case Should = 'should';
    case Could = 'could';
}
