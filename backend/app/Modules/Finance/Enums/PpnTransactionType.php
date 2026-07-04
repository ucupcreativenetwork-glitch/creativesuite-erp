<?php

namespace App\Modules\Finance\Enums;

enum PpnTransactionType: string
{
    case Output = 'OUTPUT';
    case Input = 'INPUT';
}