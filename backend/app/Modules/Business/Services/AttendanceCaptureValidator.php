<?php

namespace App\Modules\Business\Services;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;

class AttendanceCaptureValidator
{
    public function __construct(protected HrSettingsService $hrSettings) {}

    public function shouldEnforce(bool $isMobileClient): bool
    {
        $config = config('hr.attendance_capture', []);

        if ($config['strict_mobile_only'] ?? true) {
            return $isMobileClient;
        }

        return (bool) (($config['require_gps'] ?? false) || ($config['require_selfie'] ?? false));
    }

    public function isMobileClient(?string $clientHeader): bool
    {
        $expected = (string) (config('hr.attendance_capture.mobile_client_header') ?? 'creativesuite-hr-mobile');

        return $clientHeader === $expected;
    }

    public function validateGps(
        User $user,
        ?float $latitude,
        ?float $longitude,
        ?float $accuracyMeters,
        bool $enforce,
    ): void {
        $config = $this->hrSettings->attendanceCaptureFor($user);

        if (! $enforce || ! ($config['require_gps'] ?? false)) {
            return;
        }

        if ($latitude === null || $longitude === null || $accuracyMeters === null) {
            throw new ApiException('Lokasi GPS wajib untuk absensi.', 422, 'GPS_REQUIRED');
        }

        $maxAccuracy = (float) ($config['max_gps_accuracy_m'] ?? 80);
        if ($accuracyMeters > $maxAccuracy) {
            throw new ApiException(
                "Akurasi GPS terlalu rendah ({$accuracyMeters} m). Maksimal {$maxAccuracy} m — coba di area terbuka.",
                422,
                'GPS_ACCURACY_TOO_LOW',
            );
        }

        $geofence = $this->resolveGeofence($user);
        if (! $geofence['enabled']) {
            return;
        }

        if ($geofence['latitude'] === null || $geofence['longitude'] === null) {
            throw new ApiException('Geofence kantor belum dikonfigurasi. Hubungi HRD.', 500, 'GEOFENCE_NOT_CONFIGURED');
        }

        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            (float) $geofence['latitude'],
            (float) $geofence['longitude'],
        );

        $radius = (float) $geofence['radius_m'];
        if ($distance > $radius) {
            $locationLabel = $geofence['branch_name'] ? "kantor ({$geofence['branch_name']})" : 'area kantor';

            throw new ApiException(
                'Anda berada di luar '.$locationLabel.' ('.round($distance).' m dari titik absensi). Radius diizinkan: '.(int) $radius.' m.',
                422,
                'GEOFENCE_OUT_OF_RANGE',
            );
        }
    }

    public function validatePhoto(User $user, ?UploadedFile $photo, bool $enforce): void
    {
        $config = $this->hrSettings->attendanceCaptureFor($user);

        if (! $enforce || ! ($config['require_selfie'] ?? false)) {
            return;
        }

        if (! $photo) {
            throw new ApiException('Foto selfie wajib untuk absensi.', 422, 'SELFIE_REQUIRED');
        }

        if (! $photo->isValid()) {
            throw new ApiException('File foto tidak valid.', 422, 'SELFIE_INVALID');
        }
    }

    public function settings(User $user): array
    {
        $config = $this->hrSettings->attendanceCaptureFor($user);
        $policy = $this->hrSettings->resolve($user);
        $geofence = $this->resolveGeofence($user);

        return [
            'work_start' => $policy['work_start'],
            'work_end' => $policy['work_end'],
            'late_grace_minutes' => $policy['late_grace_minutes'],
            'require_gps' => (bool) ($config['require_gps'] ?? true),
            'require_selfie' => (bool) ($config['require_selfie'] ?? true),
            'max_gps_accuracy_m' => (int) ($config['max_gps_accuracy_m'] ?? 80),
            'geofence_enabled' => (bool) $geofence['enabled'],
            'geofence_radius_m' => (int) $geofence['radius_m'],
            'geofence_latitude' => $geofence['latitude'],
            'geofence_longitude' => $geofence['longitude'],
            'geofence_branch_name' => $geofence['branch_name'],
            'geofence_source' => $geofence['source'],
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     latitude: float|null,
     *     longitude: float|null,
     *     radius_m: int,
     *     source: string,
     *     branch_name: string|null
     * }
     */
    public function resolveGeofence(User $user): array
    {
        $branch = $user->relationLoaded('defaultBranch')
            ? $user->defaultBranch
            : Branch::query()->find($user->default_branch_id);

        if (
            $branch?->attendance_geofence_enabled
            && $branch->attendance_latitude !== null
            && $branch->attendance_longitude !== null
        ) {
            return [
                'enabled' => true,
                'latitude' => (float) $branch->attendance_latitude,
                'longitude' => (float) $branch->attendance_longitude,
                'radius_m' => (int) ($branch->attendance_geofence_radius_m ?: 150),
                'source' => 'branch',
                'branch_name' => $branch->name,
            ];
        }

        $config = config('hr.attendance_capture', []);

        return [
            'enabled' => (bool) ($config['geofence_enabled'] ?? false),
            'latitude' => $config['geofence_latitude'] ?? null,
            'longitude' => $config['geofence_longitude'] ?? null,
            'radius_m' => (int) ($config['geofence_radius_m'] ?? 150),
            'source' => 'env',
            'branch_name' => null,
        ];
    }

    protected function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}