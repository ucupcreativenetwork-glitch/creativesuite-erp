<?php

namespace App\Modules\Business\Enums;

enum LeaveType: string
{
    case Annual = 'ANNUAL';
    case Sick = 'SICK';
    case Maternity = 'MATERNITY';
    case Paternity = 'PATERNITY';
    case Marriage = 'MARRIAGE';
    case Bereavement = 'BEREAVEMENT';
    case Important = 'IMPORTANT';
    case LongLeave = 'LONG_LEAVE';
    case Menstrual = 'MENSTRUAL';
    case Unpaid = 'UNPAID';
    case Permission = 'PERMISSION';
    case Other = 'OTHER';
}