<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\MilestoneStatus;
use App\Modules\Business\Models\Milestone;
use App\Modules\Business\Models\Project;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Services\InvoiceService;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MilestoneService
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function list(User $user, string $projectPublicId)
    {
        $this->assertPermission($user, 'prj.milestone.read');
        $project = $this->findProject($user, $projectPublicId);

        return Milestone::query()
            ->where('project_id', $project->id)
            ->with('invoice')
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->get();
    }

    public function create(User $user, string $projectPublicId, array $data): Milestone
    {
        $this->assertPermission($user, 'prj.milestone.create');
        $project = $this->findProject($user, $projectPublicId);

        return Milestone::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'status' => MilestoneStatus::Pending,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $projectPublicId, string $milestonePublicId, array $data): Milestone
    {
        $this->assertPermission($user, 'prj.milestone.update');
        $project = $this->findProject($user, $projectPublicId);

        $milestone = Milestone::query()
            ->where('project_id', $project->id)
            ->where('public_id', $milestonePublicId)
            ->firstOrFail();

        if ($milestone->status === MilestoneStatus::Invoiced) {
            throw new ApiException('Milestone yang sudah di-invoice tidak dapat diubah.', 422, 'MILESTONE_INVOICED');
        }

        $milestone->update(collect($data)->only([
            'name', 'description', 'amount', 'due_date', 'sort_order',
        ])->filter(fn ($v) => $v !== null)->all());

        return $milestone->fresh();
    }

    public function delete(User $user, string $projectPublicId, string $milestonePublicId): void
    {
        $this->assertPermission($user, 'prj.milestone.delete');
        $project = $this->findProject($user, $projectPublicId);

        $milestone = Milestone::query()
            ->where('project_id', $project->id)
            ->where('public_id', $milestonePublicId)
            ->firstOrFail();

        if ($milestone->status === MilestoneStatus::Invoiced) {
            throw new ApiException('Milestone yang sudah di-invoice tidak dapat dihapus.', 422, 'MILESTONE_INVOICED');
        }

        $milestone->delete();
    }

    public function generateInvoice(User $user, string $projectPublicId, string $milestonePublicId): Milestone
    {
        $this->assertPermission($user, 'prj.milestone.invoice');
        $project = $this->findProject($user, $projectPublicId);

        return DB::transaction(function () use ($user, $project, $milestonePublicId) {
            $milestone = Milestone::query()
                ->where('project_id', $project->id)
                ->where('public_id', $milestonePublicId)
                ->firstOrFail();

            if ($milestone->status === MilestoneStatus::Invoiced) {
                return $milestone->load('invoice');
            }

            if ($milestone->status === MilestoneStatus::Cancelled) {
                throw new ApiException('Milestone dibatalkan.', 422, 'MILESTONE_CANCELLED');
            }

            $invoice = $this->invoiceService->createFromMilestone($user, $project, $milestone);

            $milestone->update([
                'status' => MilestoneStatus::Invoiced,
                'invoice_id' => $invoice->id,
            ]);

            return $milestone->fresh(['invoice']);
        });
    }

    public function projectMilestoneSummary(int $projectId): array
    {
        $milestones = Milestone::query()->where('project_id', $projectId)->get();

        return [
            'invoiced_milestones' => round($milestones->where('status', MilestoneStatus::Invoiced)->sum('amount'), 2),
            'pending_milestones' => round($milestones->where('status', MilestoneStatus::Pending)->sum('amount'), 2),
        ];
    }

    protected function findProject(User $user, string $publicId): Project
    {
        return Project::query()
            ->where('public_id', $publicId)
            ->where('company_id', $user->default_company_id)
            ->with('account')
            ->firstOrFail();
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}