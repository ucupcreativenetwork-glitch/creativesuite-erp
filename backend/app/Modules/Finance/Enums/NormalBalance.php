<?php

namespace App\Modules\Finance\Enums;

enum NormalBalance: string
{
    case Debit = 'DEBIT';
    case Credit = 'CREDIT';
}