<?php

namespace App\Modules\Business\Enums;

enum AttendanceStatus: string
{
    case Present = 'PRESENT';
    case Late = 'LATE';
    case Absent = 'ABSENT';
    case HalfDay = 'HALF_DAY';
    case Leave = 'LEAVE';
}