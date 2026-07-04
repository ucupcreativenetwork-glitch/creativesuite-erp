<?php

namespace App\Modules\Business\Enums;

enum StockMovementType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Adjust = 'ADJUST';
}