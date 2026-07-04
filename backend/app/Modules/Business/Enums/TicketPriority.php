<?php

namespace App\Modules\Business\Enums;

enum TicketPriority: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Urgent = 'URGENT';
}