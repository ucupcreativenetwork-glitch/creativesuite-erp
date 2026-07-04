<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\InvoiceStatus;
use App\Modules\Finance\Enums\InvoiceType;
use App\Modules\Finance\Enums\JournalStatus;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\JournalEntryLine;
use Carbon\Carbon;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generalLedger(User $user, array $filters): array
    {
        $this->assertPermission($user, 'fin.report.read');

        $accountId = $filters['account_id'] ?? null;
        if (! $accountId) {
            throw new ApiException('account_id is required.', 422, 'VALIDATION_ERROR');
        }

        $account = ChartOfAccount::query()->findOrFail($accountId);

        $query = JournalEntryLine::query()
            ->select([
                'cs_fin_journal_entry_lines.*',
                'cs_fin_journal_entries.entry_number',
                'cs_fin_journal_entries.entry_date',
                'cs_fin_journal_entries.description as entry_description',
                'cs_fin_journal_entries.journal_type',
            ])
            ->join('cs_fin_journal_entries', 'cs_fin_journal_entries.id', '=', 'cs_fin_journal_entry_lines.journal_entry_id')
            ->where('cs_fin_journal_entry_lines.account_id', $accountId)
            ->where('cs_fin_journal_entries.status', JournalStatus::Posted)
            ->where('cs_fin_journal_entries.company_id', $user->default_company_id);

        if (! empty($filters['from_date'])) {
            $query->where('cs_fin_journal_entries.entry_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('cs_fin_journal_entries.entry_date', '<=', $filters['to_date']);
        }

        $lines = $query->orderBy('cs_fin_journal_entries.entry_date')
            ->orderBy('cs_fin_journal_entry_lines.id')
            ->get();

        $isDebitNormal = $account->normal_balance->value === 'DEBIT';
        $openingBalance = $this->openingBalanceForAccount(
            $accountId,
            $user->default_company_id,
            $filters['from_date'] ?? null,
            $isDebitNormal,
        );
        $running = $openingBalance;
        $entries = [];

        foreach ($lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            if ($account->normal_balance->value === 'DEBIT') {
                $running += $debit - $credit;
            } else {
                $running += $credit - $debit;
            }

            $entries[] = [
                'entry_date' => $line->entry_date,
                'entry_number' => $line->entry_number,
                'journal_type' => $line->journal_type,
                'description' => $line->description ?? $line->entry_description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($running, 2),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'normal_balance' => $account->normal_balance,
            ],
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'closing_balance' => round($running, 2),
        ];
    }

    protected function openingBalanceForAccount(int $accountId, int $companyId, ?string $fromDate, bool $isDebitNormal): float
    {
        if (! $fromDate) {
            return 0;
        }

        $row = DB::table('cs_fin_journal_entry_lines as l')
            ->join('cs_fin_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)
            ->where('j.company_id', $companyId)
            ->where('j.status', JournalStatus::Posted)
            ->where('j.entry_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(l.debit), 0) as total_debit, COALESCE(SUM(l.credit), 0) as total_credit')
            ->first();

        $debit = (float) ($row->total_debit ?? 0);
        $credit = (float) ($row->total_credit ?? 0);

        return round($isDebitNormal ? $debit - $credit : $credit - $debit, 2);
    }

    public function trialBalance(User $user, array $filters): array
    {
        $this->assertPermission($user, 'fin.report.read');

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? now()->toDateString();

        $query = DB::table('cs_fin_journal_entry_lines as l')
            ->join('cs_fin_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('cs_fin_chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $user->default_company_id)
            ->where('j.status', JournalStatus::Posted->value)
            ->where('a.is_postable', true)
            ->where('j.entry_date', '<=', $toDate);

        if ($fromDate) {
            $query->where('j.entry_date', '>=', $fromDate);
        }

        $rows = $query
            ->groupBy('a.id', 'a.code', 'a.name', 'a.category', 'a.normal_balance')
            ->select([
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.category',
                'a.normal_balance',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            ])
            ->orderBy('a.code')
            ->get();

        $accounts = [];
        $sumDebit = 0;
        $sumCredit = 0;

        foreach ($rows as $row) {
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $accounts[] = [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'category' => $row->category,
                'normal_balance' => $row->normal_balance,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $sumDebit += $debit;
            $sumCredit += $credit;
        }

        return [
            'period' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'accounts' => $accounts,
            'totals' => [
                'debit' => round($sumDebit, 2),
                'credit' => round($sumCredit, 2),
                'is_balanced' => abs($sumDebit - $sumCredit) < 0.01,
            ],
        ];
    }

    public function profitLoss(User $user, array $filters): array
    {
        $this->assertPermission($user, 'fin.report.read');

        $fromDate = $filters['from_date'] ?? now()->startOfYear()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();

        $rows = DB::table('cs_fin_journal_entry_lines as l')
            ->join('cs_fin_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('cs_fin_chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $user->default_company_id)
            ->where('j.status', JournalStatus::Posted->value)
            ->where('a.is_postable', true)
            ->whereIn('a.category', [4, 5, 6])
            ->whereBetween('j.entry_date', [$fromDate, $toDate])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.category', 'a.normal_balance')
            ->select([
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.category',
                'a.normal_balance',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            ])
            ->orderBy('a.code')
            ->get();

        $sections = [
            4 => ['key' => 'revenue', 'label' => 'Pendapatan', 'accounts' => [], 'total' => 0],
            5 => ['key' => 'cogs', 'label' => 'HPP', 'accounts' => [], 'total' => 0],
            6 => ['key' => 'expenses', 'label' => 'Beban', 'accounts' => [], 'total' => 0],
        ];

        foreach ($rows as $row) {
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);
            $category = (int) $row->category;

            $amount = match ($category) {
                4 => $credit - $debit,
                default => $debit - $credit,
            };

            if (abs($amount) < 0.01) {
                continue;
            }

            $sections[$category]['accounts'][] = [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'amount' => round($amount, 2),
            ];
            $sections[$category]['total'] += $amount;
        }

        foreach ($sections as &$section) {
            $section['total'] = round($section['total'], 2);
        }
        unset($section);

        $revenue = $sections[4]['total'];
        $cogs = $sections[5]['total'];
        $expenses = $sections[6]['total'];

        return [
            'period' => ['from_date' => $fromDate, 'to_date' => $toDate],
            'revenue' => $sections[4],
            'cogs' => $sections[5],
            'expenses' => $sections[6],
            'gross_profit' => round($revenue - $cogs, 2),
            'net_profit' => round($revenue - $cogs - $expenses, 2),
        ];
    }

    public function balanceSheet(User $user, array $filters): array
    {
        $this->assertPermission($user, 'fin.report.read');

        $asOfDate = $filters['as_of_date'] ?? now()->toDateString();

        $rows = DB::table('cs_fin_journal_entry_lines as l')
            ->join('cs_fin_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('cs_fin_chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.company_id', $user->default_company_id)
            ->where('j.status', JournalStatus::Posted->value)
            ->where('a.is_postable', true)
            ->whereIn('a.category', [1, 2, 3])
            ->where('j.entry_date', '<=', $asOfDate)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.category', 'a.normal_balance')
            ->select([
                'a.id as account_id',
                'a.code',
                'a.name',
                'a.category',
                'a.normal_balance',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit'),
            ])
            ->orderBy('a.code')
            ->get();

        $sections = [
            1 => ['key' => 'assets', 'label' => 'Aset', 'accounts' => [], 'total' => 0],
            2 => ['key' => 'liabilities', 'label' => 'Liabilitas', 'accounts' => [], 'total' => 0],
            3 => ['key' => 'equity', 'label' => 'Ekuitas', 'accounts' => [], 'total' => 0],
        ];

        foreach ($rows as $row) {
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);
            $category = (int) $row->category;

            $amount = $row->normal_balance === 'DEBIT'
                ? $debit - $credit
                : $credit - $debit;

            if (abs($amount) < 0.01) {
                continue;
            }

            $sections[$category]['accounts'][] = [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'amount' => round($amount, 2),
            ];
            $sections[$category]['total'] += $amount;
        }

        foreach ($sections as &$section) {
            $section['total'] = round($section['total'], 2);
        }
        unset($section);

        $assets = $sections[1]['total'];
        $liabilities = $sections[2]['total'];
        $equity = $sections[3]['total'];

        return [
            'as_of_date' => $asOfDate,
            'assets' => $sections[1],
            'liabilities' => $sections[2],
            'equity' => $sections[3],
            'total_liabilities_and_equity' => round($liabilities + $equity, 2),
            'is_balanced' => abs($assets - ($liabilities + $equity)) < 0.01,
        ];
    }

    public function apAging(User $user, array $filters): array
    {
        $this->assertPermission($user, 'fin.report.read');

        $asOfDate = Carbon::parse($filters['as_of_date'] ?? now()->toDateString());

        $invoices = Invoice::query()
            ->where('company_id', $user->default_company_id)
            ->where('invoice_type', InvoiceType::Purchase)
            ->whereIn('status', [InvoiceStatus::Posted, InvoiceStatus::Paid])
            ->whereRaw('total_amount > paid_amount')
            ->orderBy('due_date')
            ->get();

        $bucketDefs = [
            ['key' => 'current', 'label' => 'Current (0–30 hari)', 'min' => 0, 'max' => 30],
            ['key' => '31_60', 'label' => '31–60 hari', 'min' => 31, 'max' => 60],
            ['key' => '61_90', 'label' => '61–90 hari', 'min' => 61, 'max' => 90],
            ['key' => 'over_90', 'label' => '> 90 hari', 'min' => 91, 'max' => null],
        ];

        $buckets = collect($bucketDefs)->map(fn ($def) => [
            'key' => $def['key'],
            'label' => $def['label'],
            'invoices' => [],
            'total_outstanding' => 0,
        ])->keyBy('key')->all();

        $grandTotal = 0;

        foreach ($invoices as $invoice) {
            $outstanding = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            if ($outstanding <= 0) {
                continue;
            }

            $referenceDate = $invoice->due_date ?? $invoice->invoice_date;
            $daysOverdue = $referenceDate->lte($asOfDate)
                ? (int) $referenceDate->diffInDays($asOfDate)
                : 0;

            $bucketKey = match (true) {
                $daysOverdue <= 30 => 'current',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucketKey]['invoices'][] = [
                'public_id' => $invoice->public_id,
                'invoice_number' => $invoice->invoice_number,
                'counterparty_name' => $invoice->counterparty_name,
                'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'outstanding' => $outstanding,
                'days_overdue' => $daysOverdue,
            ];

            $buckets[$bucketKey]['total_outstanding'] += $outstanding;
            $grandTotal += $outstanding;
        }

        foreach ($buckets as &$bucket) {
            $bucket['total_outstanding'] = round($bucket['total_outstanding'], 2);
        }
        unset($bucket);

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'buckets' => array_values($buckets),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    public function arAging(User $user, array $filters): array
    {
        $this->assertPermission($user, 'fin.report.read');

        $asOfDate = Carbon::parse($filters['as_of_date'] ?? now()->toDateString());

        $invoices = Invoice::query()
            ->where('company_id', $user->default_company_id)
            ->where('invoice_type', InvoiceType::Sales)
            ->whereIn('status', [InvoiceStatus::Posted, InvoiceStatus::Paid])
            ->whereRaw('total_amount > paid_amount')
            ->orderBy('due_date')
            ->get();

        $bucketDefs = [
            ['key' => 'current', 'label' => 'Current (0–30 hari)', 'min' => 0, 'max' => 30],
            ['key' => '31_60', 'label' => '31–60 hari', 'min' => 31, 'max' => 60],
            ['key' => '61_90', 'label' => '61–90 hari', 'min' => 61, 'max' => 90],
            ['key' => 'over_90', 'label' => '> 90 hari', 'min' => 91, 'max' => null],
        ];

        $buckets = collect($bucketDefs)->map(fn ($def) => [
            'key' => $def['key'],
            'label' => $def['label'],
            'invoices' => [],
            'total_outstanding' => 0,
        ])->keyBy('key')->all();

        $grandTotal = 0;

        foreach ($invoices as $invoice) {
            $outstanding = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            if ($outstanding <= 0) {
                continue;
            }

            $referenceDate = $invoice->due_date ?? $invoice->invoice_date;
            $daysOverdue = $referenceDate->lte($asOfDate)
                ? (int) $referenceDate->diffInDays($asOfDate)
                : 0;

            $bucketKey = match (true) {
                $daysOverdue <= 30 => 'current',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucketKey]['invoices'][] = [
                'public_id' => $invoice->public_id,
                'invoice_number' => $invoice->invoice_number,
                'counterparty_name' => $invoice->counterparty_name,
                'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'outstanding' => $outstanding,
                'days_overdue' => $daysOverdue,
            ];

            $buckets[$bucketKey]['total_outstanding'] += $outstanding;
            $grandTotal += $outstanding;
        }

        foreach ($buckets as &$bucket) {
            $bucket['total_outstanding'] = round($bucket['total_outstanding'], 2);
        }
        unset($bucket);

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'buckets' => array_values($buckets),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}