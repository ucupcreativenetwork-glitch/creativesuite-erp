<?php

namespace App\Modules\Business\Enums;

enum LeaveAccrualMode: string
{
    case Annual = 'ANNUAL';
    case Monthly = 'MONTHLY';
}