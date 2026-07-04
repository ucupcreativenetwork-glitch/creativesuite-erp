<?php

namespace App\Modules\Business\Enums;

enum LeaveBalanceEntryType: string
{
    case Accrual = 'ACCRUAL';
    case Usage = 'USAGE';
    case Adjustment = 'ADJUSTMENT';
    case CarryForward = 'CARRY_FORWARD';
    case Reversal = 'REVERSAL';
}