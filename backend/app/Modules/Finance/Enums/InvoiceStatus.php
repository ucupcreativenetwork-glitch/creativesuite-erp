<?php

namespace App\Modules\Finance\Enums;

enum InvoiceStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Paid = 'PAID';
    case Void = 'VOID';
}