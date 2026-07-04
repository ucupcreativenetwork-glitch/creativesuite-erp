<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\ProjectStatus;
use App\Modules\Business\Models\Project;
use App\Modules\Business\Models\Quotation;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\InvoiceStatus;
use App\Modules\Finance\Enums\InvoiceType;
use App\Modules\Finance\Models\Invoice;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class ProjectService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function __construct(
        protected TimesheetService $timesheetService,
        protected MilestoneService $milestoneService,
    ) {}

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'prj.project.read');

        $query = Project::query()->with('account')->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('project_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): Project
    {
        $this->assertPermission($user, 'prj.project.read');

        return Project::query()
            ->where('public_id', $publicId)
            ->with(['account', 'quotation.invoice'])
            ->firstOrFail();
    }

    public function create(User $user, array $data): Project
    {
        $this->assertPermission($user, 'prj.project.create');
        $this->assertAccountInScope($user, $data['account_id'] ?? null);

        return Project::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'project_number' => $this->generateNumber(
                new Project,
                $user->tenant_id,
                $user->default_company_id,
                'PRJ-',
                'project_number',
            ),
            'name' => $data['name'],
            'account_id' => $data['account_id'] ?? null,
            'quotation_id' => $data['quotation_id'] ?? null,
            'status' => $data['status'] ?? ProjectStatus::Active,
            'budget' => $data['budget'] ?? 0,
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function createFromQuotation(User $user, Quotation $quotation): Project
    {
        $existing = Project::query()->where('quotation_id', $quotation->id)->first();
        if ($existing) {
            return $existing;
        }

        return Project::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'project_number' => $this->generateNumber(
                new Project,
                $user->tenant_id,
                $user->default_company_id,
                'PRJ-',
                'project_number',
            ),
            'name' => "Project {$quotation->customer_name}",
            'account_id' => $quotation->account_id,
            'quotation_id' => $quotation->id,
            'status' => ProjectStatus::Active,
            'budget' => $quotation->total_amount,
            'start_date' => now()->toDateString(),
            'end_date' => $quotation->valid_until?->format('Y-m-d'),
            'notes' => "Dibuat otomatis dari penawaran {$quotation->quotation_number}.",
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $publicId, array $data): Project
    {
        $this->assertPermission($user, 'prj.project.update');

        $project = Project::query()->where('public_id', $publicId)->firstOrFail();

        if (isset($data['account_id'])) {
            $this->assertAccountInScope($user, $data['account_id']);
        }

        $project->update(array_filter($data, fn ($v) => $v !== null));

        return $project->fresh(['account', 'quotation']);
    }

    public function budgetSummary(User $user, string $publicId): array
    {
        $this->assertPermission($user, 'prj.project.read');

        $project = Project::query()->where('public_id', $publicId)->firstOrFail();
        $timesheet = $this->timesheetService->projectCostSummary($project->id);
        $milestones = $this->milestoneService->projectMilestoneSummary($project->id);

        $paymentSummary = $this->paymentSummaryForProject($project->id);
        $budget = (float) $project->budget;
        $actualCost = $timesheet['actual_cost'];
        $variance = round($budget - $actualCost, 2);
        $utilizationPct = $budget > 0 ? round(($actualCost / $budget) * 100, 1) : 0;

        return array_merge([
            'budget' => $budget,
            'actual_cost' => $actualCost,
            'variance' => $variance,
            'utilization_pct' => $utilizationPct,
            'total_hours' => $timesheet['total_hours'],
            'billable_hours' => $timesheet['billable_hours'],
            'invoiced_milestones' => $milestones['invoiced_milestones'],
            'pending_milestones' => $milestones['pending_milestones'],
        ], $paymentSummary);
    }

    public function listInvoices(User $user, string $publicId)
    {
        $this->assertPermission($user, 'prj.project.read');

        $project = Project::query()->where('public_id', $publicId)->firstOrFail();

        return Invoice::query()
            ->where('project_id', $project->id)
            ->where('invoice_type', InvoiceType::Sales)
            ->orderByDesc('invoice_date')
            ->get();
    }

    protected function paymentSummaryForProject(int $projectId): array
    {
        $invoices = Invoice::query()
            ->where('project_id', $projectId)
            ->where('invoice_type', InvoiceType::Sales)
            ->get(['status', 'total_amount', 'paid_amount']);

        $posted = $invoices->where('status', InvoiceStatus::Posted);
        $totalInvoiced = round($posted->sum('total_amount'), 2);
        $totalCollected = round($posted->sum('paid_amount'), 2);
        $outstandingAr = round($totalInvoiced - $totalCollected, 2);
        $draftInvoices = round(
            $invoices->where('status', InvoiceStatus::Draft)->sum('total_amount'),
            2,
        );

        return [
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalCollected,
            'outstanding_ar' => $outstandingAr,
            'draft_invoices' => $draftInvoices,
            'invoice_count' => $invoices->count(),
        ];
    }

    public function delete(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'prj.project.delete');

        $project = Project::query()->where('public_id', $publicId)->firstOrFail();

        if ($project->status === ProjectStatus::Completed) {
            throw new ApiException('Proyek selesai tidak dapat dihapus.', 422, 'PROJECT_COMPLETED');
        }

        $project->delete();
    }
}
