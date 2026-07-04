<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\Payment;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class BankReconciliationService
{
    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.bank_recon.read');

        $query = BankStatementLine::query()
            ->with(['bankAccount', 'matchedPayment'])
            ->orderByDesc('transaction_date');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['bank_account_id'])) {
            $query->where('bank_account_id', $filters['bank_account_id']);
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    public function create(User $user, array $data): BankStatementLine
    {
        $this->assertPermission($user, 'fin.bank_recon.create');
        $this->validateBankAccountInScope($user, (int) $data['bank_account_id']);

        return BankStatementLine::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'bank_account_id' => $data['bank_account_id'],
            'transaction_date' => $data['transaction_date'],
            'description' => $data['description'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'debit' => $data['debit'] ?? 0,
            'credit' => $data['credit'] ?? 0,
            'status' => 'UNMATCHED',
            'created_by' => $user->id,
        ]);
    }

    public function match(User $user, string $linePublicId, string $paymentPublicId): BankStatementLine
    {
        $this->assertPermission($user, 'fin.bank_recon.match');

        $line = BankStatementLine::query()->where('public_id', $linePublicId)->firstOrFail();
        $payment = Payment::query()->where('public_id', $paymentPublicId)->firstOrFail();

        if ($line->status === 'MATCHED') {
            throw new ApiException('Baris sudah dicocokkan.', 422, 'ALREADY_MATCHED');
        }

        if (BankStatementLine::query()->where('matched_payment_id', $payment->id)->exists()) {
            throw new ApiException('Payment sudah dicocokkan dengan mutasi lain.', 422, 'PAYMENT_ALREADY_MATCHED');
        }

        $lineAmount = round(max((float) $line->debit, (float) $line->credit), 2);
        $paymentAmount = round((float) $payment->amount, 2);

        if ($lineAmount <= 0) {
            throw new ApiException('Baris mutasi tidak memiliki nilai debit/kredit.', 422, 'INVALID_STATEMENT_AMOUNT');
        }

        if (abs($lineAmount - $paymentAmount) > 0.01) {
            throw new ApiException(
                "Nominal tidak cocok: mutasi {$lineAmount}, payment {$paymentAmount}.",
                422,
                'AMOUNT_MISMATCH',
            );
        }

        $line->update([
            'matched_payment_id' => $payment->id,
            'status' => 'MATCHED',
        ]);

        return $line->fresh(['bankAccount', 'matchedPayment']);
    }

    public function unmatchedPayments(User $user)
    {
        $this->assertPermission($user, 'fin.bank_recon.read');

        $matchedIds = BankStatementLine::query()
            ->whereNotNull('matched_payment_id')
            ->pluck('matched_payment_id');

        return Payment::query()
            ->where('company_id', $user->default_company_id)
            ->whereNotIn('id', $matchedIds)
            ->orderByDesc('payment_date')
            ->limit(100)
            ->get();
    }

    protected function validateBankAccountInScope(User $user, int $bankAccountId): void
    {
        $exists = \App\Modules\Finance\Models\ChartOfAccount::query()
            ->where('id', $bankAccountId)
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('is_postable', true)
            ->exists();

        if (! $exists) {
            throw new ApiException('Bank account not found in current company.', 422, 'INVALID_BANK_ACCOUNT');
        }
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}