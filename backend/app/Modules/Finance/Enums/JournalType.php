<?php

namespace App\Modules\Finance\Enums;

enum JournalType: string
{
    case Sales = 'SALES';
    case Purchase = 'PURCHASE';
    case CashIn = 'CASH_IN';
    case CashOut = 'CASH_OUT';
    case Inventory = 'INVENTORY';
    case Cogs = 'COGS';
    case Payroll = 'PAYROLL';
    case Tax = 'TAX';
    case Manual = 'MANUAL';
    case Reversal = 'REVERSAL';
    case Closing = 'CLOSING';
}