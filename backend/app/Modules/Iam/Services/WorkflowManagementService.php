<?php

namespace App\Modules\Iam\Services;

use App\Modules\Iam\Models\ApprovalWorkflowConfig;
use App\Modules\Iam\Models\ApprovalWorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowManagementService
{
    /**
     * Workflow configurable per perusahaan (Head submit → approver chain).
     *
     * @var array<string, list<array{step_order: int, approver_role_code: string, can_override?: bool}>>
     */
    protected array $workflowTemplates = [
        'Workflow A — Head → GM → Owner' => [
            ['step_order' => 1, 'approver_role_code' => 'GENERAL_MANAGER'],
            ['step_order' => 2, 'approver_role_code' => 'TENANT_OWNER', 'can_override' => true],
        ],
        'Workflow B — Head → Owner' => [
            ['step_order' => 1, 'approver_role_code' => 'TENANT_OWNER', 'can_override' => true],
        ],
        'Workflow C — Head → GM' => [
            ['step_order' => 1, 'approver_role_code' => 'GENERAL_MANAGER'],
        ],
    ];

    public function ensureAllWorkflows(int $tenantId, int $companyId, ?int $createdBy = null): void
    {
        foreach ($this->workflowTemplates as $name => $steps) {
            $workflow = ApprovalWorkflowConfig::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'company_id' => $companyId, 'name' => $name],
                [
                    'public_id' => (string) Str::uuid(),
                    'module' => 'USER_CREATION',
                    'is_default' => $name === 'Workflow A — Head → GM → Owner',
                    'is_active' => true,
                    'created_by' => $createdBy,
                ],
            );

            foreach ($steps as $step) {
                ApprovalWorkflowStep::query()->firstOrCreate(
                    ['workflow_config_id' => $workflow->id, 'step_order' => $step['step_order']],
                    [
                        'approver_role_code' => $step['approver_role_code'],
                        'can_override' => $step['can_override'] ?? false,
                    ],
                );
            }
        }

        // Migrate legacy workflow names
        $legacyMap = [
            'Workflow A — GM → Owner' => 'Workflow A — Head → GM → Owner',
            'Workflow B — GM Only' => 'Workflow C — Head → GM',
            'Workflow C — Owner Only' => 'Workflow B — Head → Owner',
        ];

        foreach ($legacyMap as $old => $new) {
            ApprovalWorkflowConfig::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('name', $old)
                ->update(['name' => $new]);
        }
    }

    public function setDefault(int $companyId, string $publicId): ApprovalWorkflowConfig
    {
        $workflow = ApprovalWorkflowConfig::query()
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->where('module', 'USER_CREATION')
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($companyId, $workflow) {
            ApprovalWorkflowConfig::query()
                ->where('company_id', $companyId)
                ->where('module', 'USER_CREATION')
                ->update(['is_default' => false]);

            $workflow->update(['is_default' => true]);

            return $workflow->fresh('steps');
        });
    }

    public function list(int $companyId)
    {
        return ApprovalWorkflowConfig::query()
            ->where('company_id', $companyId)
            ->where('module', 'USER_CREATION')
            ->with('steps')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function formatWorkflow(ApprovalWorkflowConfig $workflow): array
    {
        return [
            'id' => $workflow->public_id,
            'name' => $workflow->name,
            'is_default' => $workflow->is_default,
            'is_active' => $workflow->is_active,
            'steps' => $workflow->steps->map(fn ($s) => [
                'step_order' => $s->step_order,
                'approver_role_code' => $s->approver_role_code,
            ]),
        ];
    }
}