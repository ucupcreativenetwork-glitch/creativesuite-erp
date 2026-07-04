<?php

namespace App\Modules\Finance\Enums;

enum PaymentStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Void = 'VOID';
}