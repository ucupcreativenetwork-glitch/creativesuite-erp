<?php

namespace App\Modules\Business\Services;

class NationalHolidayService
{
    /**
     * @return list<array{date: string, name: string, kind: string}>
     */
    public function forYear(int $year): array
    {
        $raw = config("indonesia_national_holidays.{$year}", []);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $date = isset($row['date']) ? (string) $row['date'] : null;
            $name = trim((string) ($row['name'] ?? ''));

            if (! $date || $name === '') {
                continue;
            }

            $items[$date] = ['date' => $date, 'name' => $name, 'kind' => (string) ($row['kind'] ?? 'national')];
        }

        ksort($items);

        return array_values($items);
    }

    /**
     * @return list<int>
     */
    public function availableYears(): array
    {
        $years = array_keys(config('indonesia_national_holidays', []));
        sort($years);

        return array_map('intval', $years);
    }
}