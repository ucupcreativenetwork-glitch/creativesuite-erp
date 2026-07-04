<?php

namespace App\Modules\Finance\Enums;

enum AccountMappingKey: string
{
    case ArAccount = 'AR_ACCOUNT';
    case ApAccount = 'AP_ACCOUNT';
    case BankAccount = 'BANK_ACCOUNT';
    case RevenueAccount = 'REVENUE_ACCOUNT';
    case ExpenseAccount = 'EXPENSE_ACCOUNT';
    case PpnOutputAccount = 'PPN_OUTPUT_ACCOUNT';
    case PpnInputAccount = 'PPN_INPUT_ACCOUNT';
    case Pph23PayableAccount = 'PPH23_PAYABLE_ACCOUNT';
    case SalaryPayableAccount = 'SALARY_PAYABLE_ACCOUNT';
    case Pph21PayableAccount = 'PPH21_PAYABLE_ACCOUNT';
    case BpjsPayableAccount = 'BPJS_PAYABLE_ACCOUNT';
    case InventoryAccount = 'INVENTORY_ACCOUNT';
    case CogsAccount = 'COGS_ACCOUNT';
}