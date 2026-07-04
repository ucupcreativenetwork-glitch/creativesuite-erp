<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Enums\InvoiceStatus;
use App\Modules\Finance\Enums\InvoiceType;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Enums\PaymentType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected TaxCalculatorService $taxCalculator,
        protected AccountMappingService $accountMapping,
        protected JournalService $journalService,
        protected Pph23Service $pph23Service,
    ) {}

    public function create(User $user, array $data): Payment
    {
        $this->assertPermission($user, 'fin.payment.create');

        $this->validateInvoiceForPayment($user, $data);
        $this->validateBankAccountInScope($user, (int) $data['bank_account_id']);

        $amount = (float) $data['amount'];
        $pph23Amount = 0;
        $netAmount = $amount;

        if (($data['payment_type'] ?? '') === PaymentType::ApDisbursement->value
            && ($data['apply_pph23'] ?? false)) {
            $dppBase = $amount;

            if (! empty($data['invoice_id'])) {
                $invoice = Invoice::query()->find($data['invoice_id']);
                if ($invoice) {
                    $dppBase = (float) $invoice->dpp_amount;
                }
            }

            $pph23 = $this->taxCalculator->calculatePph23($dppBase);
            $pph23Amount = $pph23['pph23'];
            $netAmount = round($amount - $pph23Amount, 2);
        }

        return Payment::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'branch_id' => $data['branch_id'] ?? $user->default_branch_id,
            'public_id' => (string) Str::uuid(),
            'payment_number' => $this->generateNumber($user, $data['payment_type']),
            'payment_type' => $data['payment_type'],
            'payment_date' => $data['payment_date'],
            'invoice_id' => $data['invoice_id'] ?? null,
            'counterparty_name' => $data['counterparty_name'] ?? null,
            'counterparty_npwp' => $data['counterparty_npwp'] ?? null,
            'amount' => $amount,
            'pph23_amount' => $pph23Amount,
            'net_amount' => $netAmount,
            'bank_account_id' => $data['bank_account_id'],
            'status' => PaymentStatus::Draft,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function post(User $user, string $publicId): Payment
    {
        $this->assertPermission($user, 'fin.payment.post');

        return DB::transaction(function () use ($user, $publicId) {
            $payment = Payment::query()
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->with('invoice')
                ->firstOrFail();

            if ($payment->status !== PaymentStatus::Draft) {
                throw new ApiException('Only draft payments can be posted.', 422, 'PAYMENT_NOT_DRAFT');
            }
            $journalLines = $this->buildJournalLines($payment);
            $journalType = $payment->payment_type === PaymentType::ArReceipt
                ? JournalType::CashIn
                : JournalType::CashOut;

            $journal = $this->journalService->createAuto(
                $user,
                $journalType,
                Carbon::parse($payment->payment_date),
                $journalLines,
                "Auto journal for payment {$payment->payment_number}",
                Payment::class,
                $payment->id,
                $payment->payment_number,
            );

            $payment->update([
                'status' => PaymentStatus::Posted,
                'journal_entry_id' => $journal->id,
            ]);

            if ($payment->payment_type === PaymentType::ApDisbursement && $payment->pph23_amount > 0) {
                $this->pph23Service->recordFromPayment($payment);
            }

            if ($payment->invoice_id) {
                $this->updateInvoicePaidAmount($payment);
            }

            return $payment->fresh(['invoice', 'journalEntry.lines.account', 'bankAccount']);
        });
    }

    protected function updateInvoicePaidAmount(Payment $payment): void
    {
        if (! $payment->invoice_id) {
            return;
        }

        $invoice = Invoice::query()
            ->where('id', $payment->invoice_id)
            ->lockForUpdate()
            ->first();

        if (! $invoice) {
            return;
        }

        $remaining = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
        if (round((float) $payment->amount, 2) > $remaining) {
            throw new ApiException('Payment amount exceeds invoice balance.', 422, 'OVERPAYMENT');
        }

        $newPaid = (float) $invoice->paid_amount + (float) $payment->amount;
        $status = $newPaid >= (float) $invoice->total_amount
            ? InvoiceStatus::Paid
            : InvoiceStatus::Posted;

        $invoice->update([
            'paid_amount' => $newPaid,
            'status' => $status,
        ]);
    }

    protected function buildJournalLines(Payment $payment): array
    {
        if ($payment->payment_type === PaymentType::ArReceipt) {
            return $this->buildArReceiptLines($payment);
        }

        return $this->buildApDisbursementLines($payment);
    }

    protected function buildArReceiptLines(Payment $payment): array
    {
        $arId = $this->accountMapping->getAccountId($payment->company_id, AccountMappingKey::ArAccount);

        return [
            [
                'account_id' => $payment->bank_account_id,
                'debit' => (float) $payment->amount,
                'credit' => 0,
                'description' => 'Penerimaan ke Bank',
            ],
            [
                'account_id' => $arId,
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'description' => 'Pelunasan Piutang',
            ],
        ];
    }

    protected function buildApDisbursementLines(Payment $payment): array
    {
        $apId = $this->accountMapping->getAccountId($payment->company_id, AccountMappingKey::ApAccount);

        $lines = [
            [
                'account_id' => $apId,
                'debit' => (float) $payment->amount,
                'credit' => 0,
                'description' => 'Pelunasan Hutang',
            ],
            [
                'account_id' => $payment->bank_account_id,
                'debit' => 0,
                'credit' => (float) $payment->net_amount,
                'description' => 'Pembayaran dari Bank',
            ],
        ];

        if ($payment->pph23_amount > 0) {
            $pph23Id = $this->accountMapping->getAccountId($payment->company_id, AccountMappingKey::Pph23PayableAccount);
            $lines[] = [
                'account_id' => $pph23Id,
                'debit' => 0,
                'credit' => (float) $payment->pph23_amount,
                'description' => 'Utang PPh 23',
            ];
        }

        return $lines;
    }

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.payment.read');

        $query = Payment::query()->with(['invoice', 'bankAccount'])->orderByDesc('payment_date');

        if (! empty($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): Payment
    {
        $this->assertPermission($user, 'fin.payment.read');

        return Payment::query()
            ->where('public_id', $publicId)
            ->with(['invoice', 'bankAccount', 'journalEntry.lines.account'])
            ->firstOrFail();
    }

    protected function generateNumber(User $user, string $type): string
    {
        $prefix = $type === PaymentType::ArReceipt->value ? 'RCV-' : 'PAY-';
        $prefix .= now()->format('Ym').'-';

        $last = Payment::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('payment_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('payment_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected function validateInvoiceForPayment(User $user, array $data): void
    {
        if (empty($data['invoice_id'])) {
            return;
        }

        $invoice = Invoice::query()
            ->where('id', $data['invoice_id'])
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->first();

        if (! $invoice) {
            throw new ApiException('Invoice not found in current company.', 422, 'INVALID_INVOICE');
        }

        if (! in_array($invoice->status, [InvoiceStatus::Posted, InvoiceStatus::Paid], true)) {
            throw new ApiException('Only posted invoices can receive payments.', 422, 'INVOICE_NOT_POSTED');
        }

        $expectedType = ($data['payment_type'] ?? '') === PaymentType::ArReceipt->value
            ? InvoiceType::Sales
            : InvoiceType::Purchase;

        if ($invoice->invoice_type !== $expectedType) {
            throw new ApiException('Payment type does not match invoice type.', 422, 'INVOICE_TYPE_MISMATCH');
        }

        $remaining = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
        if (round((float) $data['amount'], 2) > $remaining) {
            throw new ApiException('Payment amount exceeds invoice balance.', 422, 'OVERPAYMENT');
        }
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