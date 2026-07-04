<?php

namespace App\Modules\Iam\Enums;

enum UserRequestStatus: string
{
    case Draft = 'DRAFT';
    case Pending = 'PENDING';
    case InReview = 'IN_REVIEW';
    case RevisionRequested = 'REVISION_REQUESTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}