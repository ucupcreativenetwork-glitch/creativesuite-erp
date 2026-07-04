<?php

namespace App\Modules\Finance\Data;

use App\Modules\Finance\Enums\AccountCategory;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\NormalBalance;

class DefaultCoaTemplate
{
    public static function accounts(): array
    {
        return [
            ['code' => '1-00-000', 'name' => 'Aset', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Debit, 'is_postable' => false, 'parent_code' => null],
            ['code' => '1-10-000', 'name' => 'Aset Lancar', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Debit, 'is_postable' => false, 'parent_code' => '1-00-000'],
            ['code' => '1-11-100', 'name' => 'Piutang Usaha', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '1-10-000', 'mapping' => AccountMappingKey::ArAccount],
            ['code' => '1-12-100', 'name' => 'Kas', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '1-10-000'],
            ['code' => '1-12-110', 'name' => 'Bank', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '1-10-000', 'mapping' => AccountMappingKey::BankAccount],
            ['code' => '1-13-100', 'name' => 'PPN Masukan', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '1-10-000', 'mapping' => AccountMappingKey::PpnInputAccount],
            ['code' => '1-14-100', 'name' => 'Persediaan Barang', 'category' => AccountCategory::Asset, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '1-10-000', 'mapping' => AccountMappingKey::InventoryAccount],
            ['code' => '2-00-000', 'name' => 'Liabilitas', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Credit, 'is_postable' => false, 'parent_code' => null],
            ['code' => '2-10-000', 'name' => 'Liabilitas Lancar', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Credit, 'is_postable' => false, 'parent_code' => '2-00-000'],
            ['code' => '2-11-100', 'name' => 'Utang PPN', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '2-10-000', 'mapping' => AccountMappingKey::PpnOutputAccount],
            ['code' => '2-12-100', 'name' => 'Hutang Usaha', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '2-10-000', 'mapping' => AccountMappingKey::ApAccount],
            ['code' => '2-13-100', 'name' => 'Utang PPh 23', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '2-10-000', 'mapping' => AccountMappingKey::Pph23PayableAccount],
            ['code' => '2-14-100', 'name' => 'Utang Gaji', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '2-10-000', 'mapping' => AccountMappingKey::SalaryPayableAccount],
            ['code' => '2-15-100', 'name' => 'Utang PPh 21', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '2-10-000', 'mapping' => AccountMappingKey::Pph21PayableAccount],
            ['code' => '2-16-100', 'name' => 'Utang BPJS', 'category' => AccountCategory::Liability, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '2-10-000', 'mapping' => AccountMappingKey::BpjsPayableAccount],
            ['code' => '3-00-000', 'name' => 'Ekuitas', 'category' => AccountCategory::Equity, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Credit, 'is_postable' => false, 'parent_code' => null],
            ['code' => '3-10-100', 'name' => 'Modal Disetor', 'category' => AccountCategory::Equity, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '3-00-000'],
            ['code' => '4-00-000', 'name' => 'Pendapatan', 'category' => AccountCategory::Revenue, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Credit, 'is_postable' => false, 'parent_code' => null],
            ['code' => '4-10-100', 'name' => 'Pendapatan Jasa', 'category' => AccountCategory::Revenue, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true, 'parent_code' => '4-00-000', 'mapping' => AccountMappingKey::RevenueAccount],
            ['code' => '5-00-000', 'name' => 'Harga Pokok Penjualan', 'category' => AccountCategory::Cogs, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Debit, 'is_postable' => false, 'parent_code' => null],
            ['code' => '5-10-100', 'name' => 'HPP Jasa', 'category' => AccountCategory::Cogs, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '5-00-000', 'mapping' => AccountMappingKey::CogsAccount],
            ['code' => '6-00-000', 'name' => 'Beban', 'category' => AccountCategory::Expense, 'account_type' => AccountType::Header, 'normal_balance' => NormalBalance::Debit, 'is_postable' => false, 'parent_code' => null],
            ['code' => '6-10-100', 'name' => 'Beban Gaji', 'category' => AccountCategory::Expense, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '6-00-000', 'mapping' => AccountMappingKey::ExpenseAccount],
            ['code' => '6-20-100', 'name' => 'Beban Operasional', 'category' => AccountCategory::Expense, 'account_type' => AccountType::Detail, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true, 'parent_code' => '6-00-000'],
        ];
    }
}