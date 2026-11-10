<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Dropped = 'dropped';
}
