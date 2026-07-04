<?php

namespace App\Modules\Business\Enums;

enum QuotationStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
}