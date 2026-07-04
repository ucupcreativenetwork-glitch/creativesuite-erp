<?php

namespace App\Modules\Business\Enums;

enum MilestoneStatus: string
{
    case Pending = 'PENDING';
    case Invoiced = 'INVOICED';
    case Cancelled = 'CANCELLED';
}