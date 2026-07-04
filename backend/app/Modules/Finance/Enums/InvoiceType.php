<?php

namespace App\Modules\Finance\Enums;

enum InvoiceType: string
{
    case Sales = 'SALES';
    case Purchase = 'PURCHASE';
}