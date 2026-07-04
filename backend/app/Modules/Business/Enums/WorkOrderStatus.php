<?php

namespace App\Modules\Business\Enums;

enum WorkOrderStatus: string
{
    case Scheduled = 'SCHEDULED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}