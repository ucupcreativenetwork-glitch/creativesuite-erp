<?php

namespace App\Modules\Business\Enums;

enum AccountType: string
{
    case Customer = 'CUSTOMER';
    case Vendor = 'VENDOR';
    case Both = 'BOTH';
}