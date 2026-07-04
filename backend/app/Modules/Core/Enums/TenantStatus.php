<?php

namespace App\Modules\Core\Enums;

enum TenantStatus: string
{
    case Trial = 'TRIAL';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Cancelled = 'CANCELLED';
}