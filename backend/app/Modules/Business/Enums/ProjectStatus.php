<?php

namespace App\Modules\Business\Enums;

enum ProjectStatus: string
{
    case Active = 'ACTIVE';
    case OnHold = 'ON_HOLD';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}