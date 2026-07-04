<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\JournalStatus;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalEntryLine;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalService
{
    public function __construct(
        protected FiscalPeriodService $fiscalPeriodService,
    ) {}

    public function createManual(User $user, array $data): JournalEntry
    {
        $this->assertPermission($user, 'fin.journal.create');

        return DB::transaction(function () use ($user, $data) {
            $entryDate = Carbon::parse($data['entry_date']);
            $period = $this->fiscalPeriodService->resolveForDate($user->default_company_id, $entryDate);
            $this->fiscalPeriodService->assertOpen($period);

            $lines = $data['lines'];
            $this->validateLines($lines);

            $totals = $this->calculateTotals($lines);

            $entry = JournalEntry::create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'branch_id' => $data['branch_id'] ?? $user->default_branch_id,
                'public_id' => (string) Str::uuid(),
                'entry_number' => $this->generateEntryNumber($user->tenant_id, $user->default_company_id),
                'entry_date' => $entryDate,
                'journal_type' => JournalType::Manual,
                'status' => JournalStatus::Draft,
                'fiscal_period_id' => $period->id,
                'description' => $data['description'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'total_debit' => $totals['debit'],
                'total_credit' => $totals['credit'],
                'created_by' => $user->id,
            ]);

            $this->createLines($entry, $user->tenant_id, $lines);

            if ($data['post_immediately'] ?? false) {
                return $this->postEntry($user, $entry);
            }

            return $entry->load('lines.account');
        });
    }

    public function createAuto(
        User $user,
        JournalType $type,
        Carbon $entryDate,
        array $lines,
        ?string $description = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $referenceNo = null,
    ): JournalEntry {
        return DB::transaction(function () use ($user, $type, $entryDate, $lines, $description, $sourceType, $sourceId, $referenceNo) {
            $period = $this->fiscalPeriodService->resolveForDate($user->default_company_id, $entryDate);
            $this->fiscalPeriodService->assertOpen($period);
            $this->validateLines($lines);
            $totals = $this->calculateTotals($lines);

            $entry = JournalEntry::create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'branch_id' => $user->default_branch_id,
                'public_id' => (string) Str::uuid(),
                'entry_number' => $this->generateEntryNumber($user->tenant_id, $user->default_company_id),
                'entry_date' => $entryDate,
                'journal_type' => $type,
                'status' => JournalStatus::Draft,
                'fiscal_period_id' => $period->id,
                'description' => $description,
                'reference_no' => $referenceNo,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'total_debit' => $totals['debit'],
                'total_credit' => $totals['credit'],
                'created_by' => $user->id,
            ]);

            $this->createLines($entry, $user->tenant_id, $lines);

            return $this->postEntry($user, $entry);
        });
    }

    public function post(User $user, string $publicId): JournalEntry
    {
        $this->assertPermission($user, 'fin.journal.post');

        $entry = JournalEntry::query()->where('public_id', $publicId)->firstOrFail();

        return DB::transaction(fn () => $this->postEntry($user, $entry));
    }

    public function void(User $user, string $publicId, ?string $reason = null): array
    {
        $this->assertPermission($user, 'fin.journal.post');

        return DB::transaction(function () use ($user, $publicId, $reason) {
            $entry = JournalEntry::query()
                ->where('public_id', $publicId)
                ->with('lines')
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status !== JournalStatus::Posted) {
                throw new ApiException('Only posted journals can be voided.', 422, 'JOURNAL_NOT_POSTED');
            }

            if ($entry->reversal_of_id) {
                throw new ApiException('Reversal journals cannot be voided.', 422, 'JOURNAL_IS_REVERSAL');
            }

            $reversalDate = Carbon::now();
            $period = $this->fiscalPeriodService->resolveForDate($user->default_company_id, $reversalDate);
            $this->fiscalPeriodService->assertOpen($period);

            $reversalLines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => 'Pembalikan: '.($line->description ?? $entry->entry_number),
            ])->all();

            $this->validateLines($reversalLines);
            $totals = $this->calculateTotals($reversalLines);

            $reversal = JournalEntry::create([
                'tenant_id' => $entry->tenant_id,
                'company_id' => $entry->company_id,
                'branch_id' => $entry->branch_id,
                'public_id' => (string) Str::uuid(),
                'entry_number' => $this->generateEntryNumber($entry->tenant_id, $entry->company_id),
                'entry_date' => $reversalDate,
                'journal_type' => JournalType::Manual,
                'status' => JournalStatus::Draft,
                'fiscal_period_id' => $period->id,
                'description' => 'Reversal of '.$entry->entry_number.($reason ? " — {$reason}" : ''),
                'reference_no' => $entry->entry_number,
                'reversal_of_id' => $entry->id,
                'total_debit' => $totals['debit'],
                'total_credit' => $totals['credit'],
                'created_by' => $user->id,
            ]);

            $this->createLines($reversal, $entry->tenant_id, $reversalLines);
            $this->postEntry($user, $reversal);

            $entry->update([
                'status' => JournalStatus::Void,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
            ]);

            return [
                'voided' => $entry->fresh(['lines.account', 'fiscalPeriod']),
                'reversal' => $reversal->fresh(['lines.account', 'fiscalPeriod']),
            ];
        });
    }

    protected function postEntry(User $user, JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== JournalStatus::Draft) {
            throw new ApiException('Only draft journals can be posted.', 422, 'JOURNAL_NOT_DRAFT');
        }

        $period = $entry->fiscalPeriod;
        $this->fiscalPeriodService->assertOpen($period);

        if (abs((float) $entry->total_debit - (float) $entry->total_credit) > 0.001) {
            throw new ApiException('Journal entry is not balanced.', 422, 'JOURNAL_UNBALANCED');
        }

        $entry->update([
            'status' => JournalStatus::Posted,
            'posted_at' => now(),
            'posted_by' => $user->id,
        ]);

        return $entry->fresh(['lines.account', 'fiscalPeriod']);
    }

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.journal.read');

        $query = JournalEntry::query()
            ->with(['lines.account', 'fiscalPeriod'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['journal_type'])) {
            $query->where('journal_type', $filters['journal_type']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('entry_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('entry_date', '<=', $filters['to_date']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): JournalEntry
    {
        $this->assertPermission($user, 'fin.journal.read');

        return JournalEntry::query()
            ->where('public_id', $publicId)
            ->with(['lines.account', 'fiscalPeriod'])
            ->firstOrFail();
    }

    protected function createLines(JournalEntry $entry, int $tenantId, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $account = ChartOfAccount::query()
                ->withoutGlobalScopes()
                ->where('id', $line['account_id'])
                ->where('tenant_id', $tenantId)
                ->where('company_id', $entry->company_id)
                ->firstOrFail();

            if (! $account->is_postable || ! $account->is_active) {
                throw new ApiException(
                    "Account {$account->code} is not postable.",
                    422,
                    'ACCOUNT_NOT_POSTABLE',
                );
            }

            JournalEntryLine::create([
                'tenant_id' => $tenantId,
                'journal_entry_id' => $entry->id,
                'line_number' => $index + 1,
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
            ]);
        }
    }

    protected function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new ApiException('Journal must have at least 2 lines.', 422, 'JOURNAL_MIN_LINES');
        }

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw new ApiException('Each line must have either debit or credit.', 422, 'JOURNAL_LINE_INVALID');
            }
        }

        $totals = $this->calculateTotals($lines);

        if (abs($totals['debit'] - $totals['credit']) > 0.001) {
            throw new ApiException('Debit and credit must be equal.', 422, 'JOURNAL_UNBALANCED');
        }
    }

    protected function calculateTotals(array $lines): array
    {
        $debit = 0;
        $credit = 0;

        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        return [
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ];
    }

    protected function generateEntryNumber(int $tenantId, int $companyId): string
    {
        $prefix = 'JE-'.now()->format('Ym').'-';
        $last = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('entry_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('entry_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}