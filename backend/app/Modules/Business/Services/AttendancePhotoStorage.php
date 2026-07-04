<?php

namespace App\Modules\Business\Services;

use App\Modules\Core\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttendancePhotoStorage
{
    public function storeClockPhoto(User $user, UploadedFile $photo, string $direction): string
    {
        $extension = $photo->guessExtension() ?: 'jpg';
        $filename = sprintf(
            '%s_%s_%s.%s',
            $direction,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension,
        );

        return $photo->storeAs(
            "attendance-photos/{$user->tenant_id}/{$user->default_company_id}",
            $filename,
            'public',
        );
    }
}