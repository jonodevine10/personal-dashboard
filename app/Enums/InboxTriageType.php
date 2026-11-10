<?php

namespace App\Enums;

enum InboxTriageType: string
{
    case Task = 'task';
    case ProjectNote = 'project_note';
    case ParkingLot = 'parking_lot';
}
