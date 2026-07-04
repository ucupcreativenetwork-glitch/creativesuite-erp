<?php

namespace App\Modules\Finance\Enums;

enum AccountCategory: int
{
    case Asset = 1;
    case Liability = 2;
    case Equity = 3;
    case Revenue = 4;
    case Cogs = 5;
    case Expense = 6;
    case Other = 7;
}