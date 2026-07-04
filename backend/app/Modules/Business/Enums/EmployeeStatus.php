<?php

namespace App\Modules\Business\Enums;

enum EmployeeStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Terminated = 'TERMINATED';
}