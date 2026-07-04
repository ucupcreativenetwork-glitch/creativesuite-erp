<?php

namespace App\Modules\Finance\Enums;

enum JournalStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Void = 'VOID';
}