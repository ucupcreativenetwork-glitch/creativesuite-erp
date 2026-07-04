<?php

namespace App\Modules\Finance\Enums;

enum PaymentType: string
{
    case ArReceipt = 'AR_RECEIPT';
    case ApDisbursement = 'AP_DISBURSEMENT';
}