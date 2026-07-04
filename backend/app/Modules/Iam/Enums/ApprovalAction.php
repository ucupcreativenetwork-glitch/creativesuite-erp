<?php

namespace App\Modules\Iam\Enums;

enum ApprovalAction: string
{
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case RevisionRequested = 'REVISION_REQUESTED';
    case Cancelled = 'CANCELLED';
    case Overridden = 'OVERRIDDEN';
}