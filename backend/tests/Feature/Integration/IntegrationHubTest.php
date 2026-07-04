<?php

namespace Tests\Feature\Integration;

use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvStockBalance;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Integration\Models\IntegrationApiKey;
use App\Modules\Integration\Services\ApiKeyService;
use App\Modules\Integration\Services\AutoReorderService;
use App\Modules\Integration\Services\ConnectorService;
use Database\Seeders\DemoAgencySeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(DemoAgencySeeder::class);

        $this->owner = User::query()->where('email', 'admin@demo.id')->firstOrFail();
        $this->token = auth('api')->login($this->owner);
    }

    public function test_can_create_api_key_and_import_attendance(): void
    {
        $result = app(ApiKeyService::class)->create($this->owner, [
            'name' => 'Attendance App',
            'scopes' => ['attendance.write', 'attendance.read'],
        ]);

        $plainKey = $result['plain_text_key'];
        $employee = Employee::query()->where('company_id', $this->owner->default_company_id)->firstOrFail();

        $this->withHeader('X-Api-Key', $plainKey)
            ->postJson('/api/v1/external/attendance/import', [
                'source' => 'test',
                'records' => [[
                    'employee_number' => $employee->employee_number,
                    'attendance_date' => now()->toDateString(),
                    'clock_in_at' => now()->setTime(8, 0)->toIso8601String(),
                    'clock_out_at' => now()->setTime(17, 0)->toIso8601String(),
                    'status' => 'PRESENT',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->withHeader('X-Api-Key', $plainKey)
            ->getJson('/api/v1/external/attendance')
            ->assertOk();
    }

    public function test_connector_push_records_attendance(): void
    {
        $result = app(ConnectorService::class)->create($this->owner, [
            'name' => 'ZKTeco Lobby',
            'connector_type' => 'zkteco',
        ]);

        $employee = Employee::query()->where('company_id', $this->owner->default_company_id)->firstOrFail();

        $this->withHeader('X-Connector-Token', $result['ingest_token'])
            ->postJson('/api/v1/external/connectors/push', [
                'records' => [[
                    'pin' => $employee->employee_number,
                    'timestamp' => now()->setTime(8, 5)->toIso8601String(),
                    'type' => 'in',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);
    }

    public function test_auto_reorder_creates_purchase_order(): void
    {
        $warehouse = InvWarehouse::query()->where('company_id', $this->owner->default_company_id)->firstOrFail();
        $item = InvItem::query()->where('company_id', $this->owner->default_company_id)->firstOrFail();
        $item->update(['reorder_level' => 100]);

        InvStockBalance::query()
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->update(['quantity_on_hand' => 5]);

        app(AutoReorderService::class)->create($this->owner, [
            'name' => 'Stok habis',
            'vendor_name' => 'Supplier Demo',
            'warehouse_id' => $warehouse->id,
            'order_multiplier' => 1,
        ]);

        $results = app(AutoReorderService::class)->runForTenant($this->owner);

        $this->assertNotEmpty($results);
        $this->assertEquals('created', $results[0]['status']);
    }

    public function test_hikvision_connector_push(): void
    {
        $result = app(ConnectorService::class)->create($this->owner, [
            'name' => 'Hikvision Pintu Utama',
            'connector_type' => 'hikvision',
            'employee_match_field' => 'employee_number',
        ]);

        $employee = Employee::query()->where('company_id', $this->owner->default_company_id)->firstOrFail();

        $this->withHeader('X-Connector-Token', $result['ingest_token'])
            ->postJson('/api/v1/external/connectors/push', [
                'AccessControllerEvent' => [
                    'employeeNoString' => $employee->employee_number,
                    'dateTime' => now()->setTime(8, 10)->format('Y-m-d\TH:i:sP'),
                    'attendanceStatus' => 'checkIn',
                    'serialNo' => 'HIK-001',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);
    }

    public function test_management_api_lists_integrations_meta(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/integrations/meta')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['events', 'scopes', 'connector_types']]);
    }

    public function test_connector_push_persists_ingest_log(): void
    {
        $result = app(ConnectorService::class)->create($this->owner, [
            'name' => 'ZKTeco Log Test',
            'connector_type' => 'zkteco',
        ]);

        $employee = Employee::query()->where('company_id', $this->owner->default_company_id)->firstOrFail();

        $this->withHeader('X-Connector-Token', $result['ingest_token'])
            ->postJson('/api/v1/external/connectors/push', [
                'records' => [[
                    'pin' => $employee->employee_number,
                    'timestamp' => now()->setTime(8, 5)->toIso8601String(),
                    'type' => 'in',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        $connectorPublicId = $result['connector']->public_id;

        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/connectors/{$connectorPublicId}/logs")
            ->assertOk()
            ->assertJsonPath('data.0.processed', 1)
            ->assertJsonPath('data.0.error_count', 0);

        $this->assertDatabaseHas('cs_int_connector_configs', [
            'public_id' => $connectorPublicId,
            'last_processed_count' => 1,
            'last_error_count' => 0,
        ]);
    }
}