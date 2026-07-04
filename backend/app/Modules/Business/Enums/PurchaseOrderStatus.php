<?php

namespace App\Modules\Business\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';
}