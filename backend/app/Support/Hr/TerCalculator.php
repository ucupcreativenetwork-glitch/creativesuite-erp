<?php

namespace App\Support\Hr;

/**
 * PPh21 TER bulanan — kategori A (TK/0) sesuai PMK terbaru (disederhanakan).
 */
class TerCalculator
{
    public function monthlyRate(float $grossIncome, string $category = 'A'): float
    {
        $brackets = config("hr.ter_brackets.{$category}", config('hr.ter_brackets.A', []));

        foreach ($brackets as $bracket) {
            $max = $bracket['max'] ?? null;
            if ($max === null || $grossIncome <= $max) {
                return (float) $bracket['rate'];
            }
        }

        return (float) ($brackets[array_key_last($brackets)]['rate'] ?? 0);
    }

    public function monthlyTax(float $grossIncome, string $category = 'A'): float
    {
        $rate = $this->monthlyRate($grossIncome, $category);

        return round($grossIncome * $rate, 2);
    }
}