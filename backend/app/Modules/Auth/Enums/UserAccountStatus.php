<?php

namespace App\Modules\Auth\Enums;

enum UserAccountStatus: string
{
    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Approved = 'APPROVED';
    case PendingActivation = 'PENDING_ACTIVATION';
    case Active = 'ACTIVE';
    case Rejected = 'REJECTED';
    case Suspended = 'SUSPENDED';
    case Disabled = 'DISABLED';
}