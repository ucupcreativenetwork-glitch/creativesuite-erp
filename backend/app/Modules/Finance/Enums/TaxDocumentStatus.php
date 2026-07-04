<?php

namespace App\Modules\Finance\Enums;

enum TaxDocumentStatus: string
{
    case Draft = 'DRAFT';
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Cancelled = 'CANCELLED';
    case Issued = 'ISSUED';
}