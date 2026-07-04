<?php

namespace App\Modules\Finance\Enums;

enum AccountType: string
{
    case Header = 'HEADER';
    case Detail = 'DETAIL';
}