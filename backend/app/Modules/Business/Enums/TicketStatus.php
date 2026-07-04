<?php

namespace App\Modules\Business\Enums;

enum TicketStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Waiting = 'WAITING';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';
}