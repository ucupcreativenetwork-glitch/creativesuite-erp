<?php

namespace App\Modules\Business\Enums;

enum AccountStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Prospect = 'PROSPECT';
}