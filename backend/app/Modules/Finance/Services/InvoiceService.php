<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Enums\InvoiceStatus;
use App\Modules\Finance\Enums\InvoiceType;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Enums\PpnTransactionType;
use App\Modules\Business\Models\Milestone;
use App\Modules\Business\Models\Project;
use App\Modules\Business\Models\PurchaseOrder;
use App\Modules\Business\Models\Quotation;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceLine;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        protected TaxCalculatorService $taxCalculator,
        protected AccountMappingService $accountMapping,
        protected JournalService $journalService,
        protected PpnService $ppnService,
    ) {}

    public function create(User $user, array $data): Invoice
    {
        return $this->persistInvoice($user, $data, requirePermission: true);
    }

    protected function persistInvoice(User $user, array $data, bool $requirePermission = true): Invoice
    {
        if ($requirePermission) {
            $this->assertPermission($user, 'fin.invoice.create');
        }

        return DB::transaction(function () use ($user, $data) {
            $company = Company::query()->findOrFail($user->default_company_id);
            $lines = $data['lines'];
            $subtotal = collect($lines)->sum(fn ($l) => round((float) $l['quantity'] * (float) $l['unit_price'], 2));

            if (isset($data['amounts'])) {
                $dpp = (float) $data['amounts']['dpp_amount'];
                $ppn = (float) $data['amounts']['ppn_amount'];
                $total = (float) $data['amounts']['total_amount'];
                $ppnRate = (float) ($data['amounts']['ppn_rate'] ?? ($dpp > 0 ? round($ppn / $dpp * 100, 2) : 0));
                $isInclusive = false;
            } else {
                $ppnRate = $company->is_pkp ? (float) ($data['ppn_rate'] ?? 12) : 0;
                $isInclusive = $company->is_pkp && (bool) ($data['is_ppn_inclusive'] ?? false);

                $tax = $company->is_pkp
                    ? $this->taxCalculator->calculatePpn($subtotal, $ppnRate, $isInclusive)
                    : ['dpp' => $subtotal, 'ppn' => 0, 'total' => $subtotal];

                $dpp = $tax['dpp'];
                $ppn = $tax['ppn'];
                $total = $tax['total'];
            }

            $invoice = Invoice::create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'branch_id' => $data['branch_id'] ?? $user->default_branch_id,
                'public_id' => (string) Str::uuid(),
                'invoice_number' => $this->generateNumber($user, $data['invoice_type']),
                'invoice_type' => $data['invoice_type'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'counterparty_name' => $data['counterparty_name'],
                'counterparty_npwp' => $data['counterparty_npwp'] ?? null,
                'counterparty_phone' => $data['counterparty_phone'] ?? null,
                'status' => InvoiceStatus::Draft,
                'subtotal' => $subtotal,
                'dpp_amount' => $dpp,
                'ppn_rate' => $ppnRate,
                'ppn_amount' => $ppn,
                'total_amount' => $total,
                'is_ppn_inclusive' => $isInclusive,
                'is_pph23_applicable' => $data['is_pph23_applicable'] ?? false,
                'notes' => $data['notes'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $i => $line) {
                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $i + 1,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'] ?? 1,
                    'unit_price' => $line['unit_price'],
                    'amount' => round((float) ($line['quantity'] ?? 1) * (float) $line['unit_price'], 2),
                    'account_id' => $line['account_id'] ?? null,
                ]);
            }

            return $invoice->load('lines');
        });
    }

    public function createFromMilestone(User $user, Project $project, Milestone $milestone): Invoice
    {
        $project->loadMissing('account');

        return $this->persistInvoice($user, [
            'invoice_type' => InvoiceType::Sales->value,
            'invoice_date' => now()->toDateString(),
            'due_date' => $milestone->due_date?->format('Y-m-d'),
            'counterparty_name' => $project->account?->name ?? $project->name,
            'counterparty_npwp' => $project->account?->npwp,
            'counterparty_phone' => $project->account?->phone,
            'notes' => trim("Invoice milestone: {$milestone->name}\nProject: {$project->project_number}"),
            'project_id' => $project->id,
            'lines' => [[
                'description' => $milestone->name,
                'quantity' => 1,
                'unit_price' => (float) $milestone->amount,
            ]],
        ], requirePermission: false);
    }

    public function createFromQuotation(User $user, Quotation $quotation): Invoice
    {
        $quotation->loadMissing(['lines', 'account']);

        $existing = Invoice::query()->where('quotation_id', $quotation->id)->first();
        if ($existing) {
            return $existing->load('lines');
        }

        $lines = $quotation->lines->map(fn ($line) => [
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
        ])->all();

        if ((float) $quotation->discount_amount > 0) {
            $lines[] = [
                'description' => 'Diskon penawaran',
                'quantity' => 1,
                'unit_price' => -1 * (float) $quotation->discount_amount,
            ];
        }

        $notes = trim(collect([
            $quotation->notes,
            "Dibuat otomatis dari penawaran {$quotation->quotation_number}.",
        ])->filter()->implode("\n"));

        $dpp = round((float) $quotation->subtotal - (float) $quotation->discount_amount, 2);

        return $this->persistInvoice($user, [
            'invoice_type' => InvoiceType::Sales->value,
            'invoice_date' => $quotation->quotation_date->format('Y-m-d'),
            'due_date' => $quotation->valid_until?->format('Y-m-d'),
            'counterparty_name' => $quotation->customer_name,
            'counterparty_npwp' => $quotation->account?->npwp,
            'counterparty_phone' => $quotation->account?->phone,
            'notes' => $notes !== '' ? $notes : null,
            'lines' => $lines,
            'quotation_id' => $quotation->id,
            'project_id' => $quotation->project_id,
            'amounts' => [
                'dpp_amount' => $dpp,
                'ppn_amount' => (float) $quotation->tax_amount,
                'total_amount' => (float) $quotation->total_amount,
            ],
        ], requirePermission: false);
    }

    public function createFromPurchaseOrder(User $user, PurchaseOrder $po): Invoice
    {
        $po->loadMissing('lines');

        $existing = Invoice::query()->where('purchase_order_id', $po->id)->first();
        if ($existing) {
            return $existing->load('lines');
        }

        $inventoryAccountId = $this->accountMapping->getAccountId(
            $user->default_company_id,
            AccountMappingKey::InventoryAccount,
        );
        $expenseAccountId = $this->accountMapping->getAccountId(
            $user->default_company_id,
            AccountMappingKey::ExpenseAccount,
        );

        $lines = $po->lines->map(fn ($line) => [
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'account_id' => $line->item_id ? $inventoryAccountId : $expenseAccountId,
        ])->all();

        return $this->persistInvoice($user, [
            'invoice_type' => InvoiceType::Purchase->value,
            'invoice_date' => now()->toDateString(),
            'due_date' => $po->expected_date?->format('Y-m-d'),
            'counterparty_name' => $po->vendor_name,
            'notes' => trim("Auto invoice from PO {$po->po_number}"),
            'purchase_order_id' => $po->id,
            'lines' => $lines,
        ], requirePermission: false);
    }

    public function createAndPostFromPurchaseOrder(User $user, PurchaseOrder $po): Invoice
    {
        $invoice = $this->createFromPurchaseOrder($user, $po);

        if ($invoice->status === InvoiceStatus::Draft) {
            return $this->post($user, $invoice->public_id, requirePermission: false);
        }

        return $invoice->fresh(['lines', 'journalEntry.lines.account']);
    }

    public function post(User $user, string $publicId, bool $requirePermission = true): Invoice
    {
        if ($requirePermission) {
            $this->assertPermission($user, 'fin.invoice.post');
        }

        $invoice = Invoice::query()->where('public_id', $publicId)->with('lines')->firstOrFail();

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ApiException('Only draft invoices can be posted.', 422, 'INVOICE_NOT_DRAFT');
        }

        return DB::transaction(function () use ($user, $invoice) {
            $company = Company::query()->findOrFail($invoice->company_id);
            $journalLines = $this->buildJournalLines($invoice, $company);

            $journalType = $invoice->invoice_type === InvoiceType::Sales
                ? JournalType::Sales
                : JournalType::Purchase;

            $journal = $this->journalService->createAuto(
                $user,
                $journalType,
                Carbon::parse($invoice->invoice_date),
                $journalLines,
                "Auto journal for invoice {$invoice->invoice_number}",
                Invoice::class,
                $invoice->id,
                $invoice->invoice_number,
            );

            $invoice->update([
                'status' => InvoiceStatus::Posted,
                'journal_entry_id' => $journal->id,
                'posted_at' => now(),
            ]);

            if ($company->is_pkp && $invoice->ppn_amount > 0) {
                $this->ppnService->recordFromInvoice($invoice);
            }

            return $invoice->fresh(['lines', 'journalEntry.lines.account']);
        });
    }

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.invoice.read');

        $query = Invoice::query()->with('lines')->orderByDesc('invoice_date');

        if (! empty($filters['invoice_type'])) {
            $query->where('invoice_type', $filters['invoice_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): Invoice
    {
        $this->assertPermission($user, 'fin.invoice.read');

        return Invoice::query()
            ->where('public_id', $publicId)
            ->with(['lines', 'journalEntry.lines.account', 'payments'])
            ->firstOrFail();
    }

    public function update(User $user, string $publicId, array $data): Invoice
    {
        $this->assertPermission($user, 'fin.invoice.update');

        $invoice = Invoice::query()->where('public_id', $publicId)->firstOrFail();

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ApiException('Only draft invoices can be updated.', 422, 'INVOICE_NOT_DRAFT');
        }

        $invoice->update(array_filter($data, fn ($v) => $v !== null));

        return $invoice->fresh(['lines', 'journalEntry.lines.account']);
    }

    protected function buildJournalLines(Invoice $invoice, Company $company): array
    {
        if ($invoice->invoice_type === InvoiceType::Sales) {
            return $this->buildSalesJournalLines($invoice, $company);
        }

        return $this->buildPurchaseJournalLines($invoice, $company);
    }

    protected function buildSalesJournalLines(Invoice $invoice, Company $company): array
    {
        $arId = $this->accountMapping->getAccountId($invoice->company_id, AccountMappingKey::ArAccount);
        $revenueId = $invoice->lines->first()?->account_id
            ?? $this->accountMapping->getAccountId($invoice->company_id, AccountMappingKey::RevenueAccount);

        $lines = [
            ['account_id' => $arId, 'debit' => (float) $invoice->total_amount, 'credit' => 0, 'description' => 'Piutang'],
            ['account_id' => $revenueId, 'debit' => 0, 'credit' => (float) $invoice->dpp_amount, 'description' => 'Pendapatan'],
        ];

        if ($company->is_pkp && $invoice->ppn_amount > 0) {
            $ppnOutputId = $this->accountMapping->getAccountId($invoice->company_id, AccountMappingKey::PpnOutputAccount);
            $lines[] = [
                'account_id' => $ppnOutputId,
                'debit' => 0,
                'credit' => (float) $invoice->ppn_amount,
                'description' => 'Utang PPN',
            ];
        }

        return $lines;
    }

    protected function buildPurchaseJournalLines(Invoice $invoice, Company $company): array
    {
        $apId = $this->accountMapping->getAccountId($invoice->company_id, AccountMappingKey::ApAccount);
        $defaultExpenseId = $this->accountMapping->getAccountId($invoice->company_id, AccountMappingKey::ExpenseAccount);

        $debitByAccount = [];
        foreach ($invoice->lines as $line) {
            $accountId = $line->account_id ?? $defaultExpenseId;
            $debitByAccount[$accountId] = ($debitByAccount[$accountId] ?? 0) + (float) $line->amount;
        }

        $lines = [];
        foreach ($debitByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'debit' => round($amount, 2),
                'credit' => 0,
                'description' => 'Beban/Pembelian',
            ];
        }

        if ($company->is_pkp && $invoice->ppn_amount > 0) {
            $ppnInputId = $this->accountMapping->getAccountId($invoice->company_id, AccountMappingKey::PpnInputAccount);
            $lines[] = [
                'account_id' => $ppnInputId,
                'debit' => (float) $invoice->ppn_amount,
                'credit' => 0,
                'description' => 'PPN Masukan',
            ];
        }

        $lines[] = [
            'account_id' => $apId,
            'debit' => 0,
            'credit' => (float) $invoice->total_amount,
            'description' => 'Hutang Usaha',
        ];

        return $lines;
    }

    protected function generateNumber(User $user, string $type): string
    {
        $prefix = $type === InvoiceType::Sales->value ? 'INV-S-' : 'INV-P-';
        $prefix .= now()->format('Ym').'-';

        $last = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

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