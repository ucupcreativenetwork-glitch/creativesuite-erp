<?php

namespace App\Modules\Finance\Enums;

enum FiscalPeriodStatus: string
{
    case Open = 'OPEN';
    case Closed = 'CLOSED';
    case Locked = 'LOCKED';
}