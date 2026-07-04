<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\InvoiceType;
use App\Modules\Finance\Enums\PpnTransactionType;
use App\Modules\Finance\Enums\TaxDocumentStatus;
use App\Modules\Finance\Models\EfakturDocument;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\PpnTransaction;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PpnService
{
    public function recordFromInvoice(Invoice $invoice): PpnTransaction
    {
        $type = $invoice->invoice_type === InvoiceType::Sales
            ? PpnTransactionType::Output
            : PpnTransactionType::Input;

        $date = Carbon::parse($invoice->invoice_date);

        return PpnTransaction::create([
            'tenant_id' => $invoice->tenant_id,
            'company_id' => $invoice->company_id,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'transaction_type' => $type,
            'transaction_date' => $invoice->invoice_date,
            'fiscal_year' => $date->year,
            'fiscal_month' => $date->month,
            'dpp_amount' => $invoice->dpp_amount,
            'ppn_rate' => $invoice->ppn_rate,
            'ppn_amount' => $invoice->ppn_amount,
            'counterparty_name' => $invoice->counterparty_name,
            'counterparty_npwp' => $invoice->counterparty_npwp,
        ]);
    }

    public function listTransactions(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.tax.ppn.read');

        $query = PpnTransaction::query()->orderByDesc('transaction_date');

        if (! empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (! empty($filters['year'])) {
            $query->where('fiscal_year', $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->where('fiscal_month', $filters['month']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function requestEfaktur(User $user, int $ppnTransactionId): EfakturDocument
    {
        $this->assertPermission($user, 'fin.tax.efaktur.create');

        $txn = PpnTransaction::query()->findOrFail($ppnTransactionId);

        if ($txn->transaction_type !== PpnTransactionType::Output) {
            throw new ApiException('e-Faktur only for output/PK transactions.', 422, 'EFAKTUR_INVALID_TYPE');
        }

        if ($txn->efaktur_document_id) {
            return EfakturDocument::query()->findOrFail($txn->efaktur_document_id);
        }

        $doc = EfakturDocument::create([
            'tenant_id' => $txn->tenant_id,
            'company_id' => $txn->company_id,
            'public_id' => (string) Str::uuid(),
            'ppn_transaction_id' => $txn->id,
            'status' => TaxDocumentStatus::Requested,
            'buyer_npwp' => $txn->counterparty_npwp,
            'buyer_name' => $txn->counterparty_name ?? 'Buyer',
            'dpp' => $txn->dpp_amount,
            'ppn' => $txn->ppn_amount,
            'total' => round((float) $txn->dpp_amount + (float) $txn->ppn_amount, 2),
            'tanggal_faktur' => $txn->transaction_date,
            'requested_at' => now(),
        ]);

        $txn->update(['efaktur_document_id' => $doc->id]);

        return $doc;
    }

    public function approveEfaktur(User $user, string $publicId, array $data): EfakturDocument
    {
        $this->assertPermission($user, 'fin.tax.efaktur.approve');

        $doc = EfakturDocument::query()->where('public_id', $publicId)->firstOrFail();

        if ($doc->status !== TaxDocumentStatus::Requested) {
            throw new ApiException('e-Faktur is not in requested status.', 422, 'EFAKTUR_INVALID_STATUS');
        }

        $doc->update([
            'nomor_faktur' => $data['nomor_faktur'],
            'djp_reference' => $data['djp_reference'] ?? null,
            'status' => TaxDocumentStatus::Approved,
            'approved_at' => now(),
        ]);

        return $doc->fresh();
    }

    public function listEfaktur(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.tax.efaktur.read');

        $query = EfakturDocument::query()->with('ppnTransaction')->orderByDesc('tanggal_faktur');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}