<?php

namespace Tests\Feature\Security;

use App\Modules\Auth\Notifications\OtpVerificationNotification;
use App\Modules\Auth\Services\UserVerificationOtpService;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\Integration\Models\WebhookEndpoint;
use App\Support\Security\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SensitiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_npwp_is_encrypted_at_rest(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $company = Company::query()->firstOrFail();
        $company->update(['npwp' => '12.345.678.9-012.000']);
        $company->refresh();

        $this->assertSame('12.345.678.9-012.000', $company->npwp);
        $this->assertNotSame('12.345.678.9-012.000', $company->getRawOriginal('npwp'));
        $this->assertTrue(SensitiveData::isEncrypted($company->getRawOriginal('npwp')));
    }

    public function test_webhook_secret_is_encrypted_at_rest(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $user = User::query()->where('email', 'admin@demo.id')->firstOrFail();

        $endpoint = WebhookEndpoint::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'name' => 'Test Hook',
            'url' => 'https://example.com/hook',
            'secret' => 'super-secret-value',
            'events' => ['attendance.clock_in'],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->assertSame('super-secret-value', $endpoint->fresh()->secret);
        $this->assertTrue(SensitiveData::isEncrypted($endpoint->getRawOriginal('secret')));
    }

    public function test_otp_code_is_hashed_not_plaintext(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        Notification::fake();

        $user = User::query()->where('email', 'admin@demo.id')->firstOrFail();
        $service = app(UserVerificationOtpService::class);
        $session = $service->generateAndSend($user);

        $record = DB::table('cs_core_user_verification_otps')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($record);
        $this->assertFalse((bool) preg_match('/^\d{6}$/', $record->otp_code));
        $this->assertSame(64, strlen($record->session_token));

        $otp = null;
        Notification::assertSentTo($user, OtpVerificationNotification::class, function (OtpVerificationNotification $notification) use (&$otp) {
            $reflection = new \ReflectionClass($notification);
            $property = $reflection->getProperty('otpCode');
            $property->setAccessible(true);
            $otp = $property->getValue($notification);

            return true;
        });

        $service->verify($user, $session['session_token'], (string) $otp);
    }

    public function test_password_reset_token_scoped_by_tenant(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $service = app(\App\Modules\Auth\Services\PasswordResetService::class);
        $service->sendResetLink('Demo Agency', 'admin@demo.id');

        $tenant = \App\Modules\Core\Models\Tenant::query()->where('slug', 'pt-demo')->firstOrFail();

        $this->assertDatabaseHas('password_reset_tokens', [
            'tenant_id' => $tenant->id,
            'email' => 'admin@demo.id',
        ]);
    }
}