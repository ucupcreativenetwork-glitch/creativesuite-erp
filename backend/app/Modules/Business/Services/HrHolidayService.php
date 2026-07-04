<?php

namespace App\Modules\Business\Services;

use App\Modules\Core\Models\Company;
use Carbon\Carbon;

class HrHolidayService
{
    public function __construct(protected NationalHolidayService $nationalHolidays) {}

    public function includesNational(Company $company): bool
    {
        $hr = $company->settings['hr'] ?? [];

        return (bool) ($hr['include_national_holidays'] ?? true);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    public function listCompanyHolidays(Company $company): array
    {
        $raw = $company->settings['hr']['holidays'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return $this->normalize($raw);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    public function listForCompany(Company $company): array
    {
        return $this->listMergedForCompany($company);
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    public function listMergedForCompany(Company $company, ?int $year = null): array
    {
        $items = [];

        foreach ($this->listCompanyHolidays($company) as $holiday) {
            $items[$holiday['date']] = $holiday;
        }

        if ($this->includesNational($company)) {
            $targetYear = $year ?? (int) Carbon::now($company->tenant?->timezone ?: config('app.timezone'))->year;

            foreach ($this->nationalHolidays->forYear($targetYear) as $holiday) {
                if (! isset($items[$holiday['date']])) {
                    $items[$holiday['date']] = [
                        'date' => $holiday['date'],
                        'name' => $holiday['name'],
                    ];
                }
            }
        }

        ksort($items);

        return array_values($items);
    }

    public function isHoliday(Company $company, string $date): bool
    {
        return $this->holidayName($company, $date) !== null;
    }

    public function holidayName(Company $company, string $date): ?string
    {
        $target = Carbon::parse($date)->toDateString();

        foreach ($this->listCompanyHolidays($company) as $holiday) {
            if ($holiday['date'] === $target) {
                return $holiday['name'];
            }
        }

        if ($this->includesNational($company)) {
            $year = (int) Carbon::parse($target)->year;

            foreach ($this->nationalHolidays->forYear($year) as $holiday) {
                if ($holiday['date'] === $target) {
                    return $holiday['name'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $holidays
     * @return list<array{date: string, name: string}>
     */
    public function normalize(array $holidays): array
    {
        $items = [];

        foreach ($holidays as $row) {
            if (! is_array($row)) {
                continue;
            }

            $date = isset($row['date']) ? Carbon::parse((string) $row['date'])->toDateString() : null;
            $name = trim((string) ($row['name'] ?? ''));

            if (! $date || $name === '') {
                continue;
            }

            $items[$date] = ['date' => $date, 'name' => $name];
        }

        ksort($items);

        return array_values($items);
    }
}