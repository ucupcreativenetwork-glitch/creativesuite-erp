<?php

namespace App\Modules\Business\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'DRAFT';
    case Calculated = 'CALCULATED';
    case Posted = 'POSTED';
}