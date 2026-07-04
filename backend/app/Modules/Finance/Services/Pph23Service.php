<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\TaxDocumentStatus;
use App\Modules\Finance\Models\EbupotDocument;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Pph23Transaction;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Pph23Service
{
    public function recordFromPayment(Payment $payment): Pph23Transaction
    {
        $date = Carbon::parse($payment->payment_date);
        $dppAmount = (float) $payment->amount;

        if ($payment->invoice_id && $payment->relationLoaded('invoice') ? $payment->invoice : $payment->invoice()->first()) {
            $dppAmount = (float) $payment->invoice->dpp_amount;
        }

        return Pph23Transaction::create([
            'tenant_id' => $payment->tenant_id,
            'company_id' => $payment->company_id,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'fiscal_year' => $date->year,
            'fiscal_month' => $date->month,
            'vendor_npwp' => $payment->counterparty_npwp,
            'vendor_name' => $payment->counterparty_name ?? 'Vendor',
            'dpp_amount' => $dppAmount,
            'pph23_rate' => 2,
            'pph23_amount' => $payment->pph23_amount,
        ]);
    }

    public function listTransactions(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.tax.pph23.read');

        $query = Pph23Transaction::query()->orderByDesc('transaction_date');

        if (! empty($filters['year'])) {
            $query->where('fiscal_year', $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->where('fiscal_month', $filters['month']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function issueEbupot(User $user, int $pph23TransactionId, array $data = []): EbupotDocument
    {
        $this->assertPermission($user, 'fin.tax.ebupot.create');

        $txn = Pph23Transaction::query()->findOrFail($pph23TransactionId);

        if ($txn->ebupot_document_id) {
            return EbupotDocument::query()->findOrFail($txn->ebupot_document_id);
        }

        $doc = EbupotDocument::create([
            'tenant_id' => $txn->tenant_id,
            'company_id' => $txn->company_id,
            'public_id' => (string) Str::uuid(),
            'pph23_transaction_id' => $txn->id,
            'nomor_bupot' => $data['nomor_bupot'] ?? $this->generateNomorBupot($txn),
            'status' => TaxDocumentStatus::Issued,
            'vendor_npwp' => $txn->vendor_npwp,
            'vendor_name' => $txn->vendor_name,
            'dpp' => $txn->dpp_amount,
            'pph23' => $txn->pph23_amount,
            'tanggal_bupot' => $txn->transaction_date,
            'djp_reference' => $data['djp_reference'] ?? null,
            'issued_at' => now(),
        ]);

        $txn->update(['ebupot_document_id' => $doc->id]);

        return $doc;
    }

    public function listEbupot(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.tax.ebupot.read');

        $query = EbupotDocument::query()->with('pph23Transaction')->orderByDesc('tanggal_bupot');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    protected function generateNomorBupot(Pph23Transaction $txn): string
    {
        return sprintf(
            'BUPOT-%04d%02d-%06d',
            $txn->fiscal_year,
            $txn->fiscal_month,
            $txn->id,
        );
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}