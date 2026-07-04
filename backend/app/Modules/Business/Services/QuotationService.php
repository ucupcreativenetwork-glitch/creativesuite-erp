<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\QuotationStatus;
use App\Modules\Business\Models\Quotation;
use App\Modules\Business\Models\QuotationLine;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Services\InvoiceService;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuotationService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'sales.quotation.read');

        $query = Quotation::query()->with('lines')->orderByDesc('quotation_date');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): Quotation
    {
        $this->assertPermission($user, 'sales.quotation.read');

        return Quotation::query()
            ->where('public_id', $publicId)
            ->with(['lines', 'account', 'invoice', 'project'])
            ->firstOrFail();
    }

    public function create(User $user, array $data): Quotation
    {
        $this->assertPermission($user, 'sales.quotation.create');
        $this->assertAccountInScope($user, $data['account_id'] ?? null);

        return DB::transaction(function () use ($user, $data) {
            $lines = $data['lines'];
            $subtotal = $this->calculateSubtotal($lines);
            $discount = (float) ($data['discount_amount'] ?? 0);
            $tax = (float) ($data['tax_amount'] ?? 0);
            $total = round($subtotal - $discount + $tax, 2);

            $quotation = Quotation::create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'public_id' => (string) Str::uuid(),
                'quotation_number' => $this->generateNumber(
                    new Quotation,
                    $user->tenant_id,
                    $user->default_company_id,
                    'QT-',
                    'quotation_number',
                ),
                'account_id' => $data['account_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'quotation_date' => $data['quotation_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'status' => QuotationStatus::Draft,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncLines($quotation, $lines);

            return $quotation->load('lines');
        });
    }

    public function update(User $user, string $publicId, array $data): Quotation
    {
        $this->assertPermission($user, 'sales.quotation.update');

        $quotation = Quotation::query()->where('public_id', $publicId)->firstOrFail();

        if ($quotation->status !== QuotationStatus::Draft) {
            throw new ApiException('Only draft quotations can be updated.', 422, 'QUOTATION_NOT_DRAFT');
        }

        if (isset($data['account_id'])) {
            $this->assertAccountInScope($user, $data['account_id']);
        }

        return DB::transaction(function () use ($quotation, $data) {
            if (isset($data['lines'])) {
                $subtotal = $this->calculateSubtotal($data['lines']);
                $discount = (float) ($data['discount_amount'] ?? $quotation->discount_amount);
                $tax = (float) ($data['tax_amount'] ?? $quotation->tax_amount);

                $quotation->update([
                    'account_id' => $data['account_id'] ?? $quotation->account_id,
                    'customer_name' => $data['customer_name'] ?? $quotation->customer_name,
                    'quotation_date' => $data['quotation_date'] ?? $quotation->quotation_date,
                    'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'total_amount' => round($subtotal - $discount + $tax, 2),
                    'notes' => $data['notes'] ?? $quotation->notes,
                ]);

                $quotation->lines()->delete();
                $this->syncLines($quotation, $data['lines']);
            } else {
                $quotation->update(array_filter($data, fn ($v) => $v !== null));
            }

            return $quotation->fresh(['lines', 'account']);
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'sales.quotation.delete');

        $quotation = Quotation::query()->where('public_id', $publicId)->firstOrFail();

        if ($quotation->status !== QuotationStatus::Draft) {
            throw new ApiException('Only draft quotations can be deleted.', 422, 'QUOTATION_NOT_DRAFT');
        }

        $quotation->delete();
    }

    public function send(User $user, string $publicId): Quotation
    {
        $this->assertPermission($user, 'sales.quotation.send');

        $quotation = Quotation::query()->where('public_id', $publicId)->firstOrFail();

        if ($quotation->status !== QuotationStatus::Draft) {
            throw new ApiException('Only draft quotations can be sent.', 422, 'QUOTATION_NOT_DRAFT');
        }

        $quotation->update(['status' => QuotationStatus::Sent]);

        return $quotation->fresh(['lines', 'account']);
    }

    public function accept(User $user, string $publicId): Quotation
    {
        $this->assertPermission($user, 'sales.quotation.accept');

        return DB::transaction(function () use ($user, $publicId) {
            $quotation = Quotation::query()
                ->where('public_id', $publicId)
                ->with(['lines', 'account', 'invoice', 'project'])
                ->firstOrFail();

            if ($quotation->status === QuotationStatus::Accepted) {
                return $quotation->fresh(['lines', 'account', 'invoice', 'project']);
            }

            if ($quotation->status !== QuotationStatus::Sent) {
                throw new ApiException('Only sent quotations can be accepted.', 422, 'QUOTATION_NOT_SENT');
            }

            $invoiceService = app(InvoiceService::class);
            $projectService = app(ProjectService::class);

            $invoice = $quotation->invoice_id
                ? $quotation->invoice
                : $invoiceService->createFromQuotation($user, $quotation);
            $project = $quotation->project_id
                ? $quotation->project
                : $projectService->createFromQuotation($user, $quotation);

            if (! $invoice->project_id) {
                $invoice->update(['project_id' => $project->id]);
            }

            $quotation->update([
                'status' => QuotationStatus::Accepted,
                'invoice_id' => $invoice->id,
                'project_id' => $project->id,
            ]);

            return $quotation->fresh(['lines', 'account', 'invoice', 'project']);
        });
    }

    protected function calculateSubtotal(array $lines): float
    {
        return round(collect($lines)->sum(fn ($l) => round((float) ($l['quantity'] ?? 1) * (float) $l['unit_price'], 2)), 2);
    }

    protected function syncLines(Quotation $quotation, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) $line['unit_price'];

            QuotationLine::create([
                'quotation_id' => $quotation->id,
                'line_number' => $i + 1,
                'description' => $line['description'],
                'quantity' => $qty,
                'unit_price' => $price,
                'amount' => round($qty * $price, 2),
            ]);
        }
    }
}