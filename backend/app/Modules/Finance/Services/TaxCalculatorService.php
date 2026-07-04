<?php

namespace App\Modules\Finance\Services;

class TaxCalculatorService
{
    public function calculatePpn(
        float $amount,
        float $ppnRate = 12,
        bool $isInclusive = false,
    ): array {
        if ($isInclusive) {
            $dpp = $this->roundMoney($amount * 11 / 12);
            $ppn = $this->roundMoney($amount - $dpp);
            $total = $this->roundMoney($amount);
        } else {
            $dpp = $this->roundMoney($amount);
            $ppn = $this->roundMoney($dpp * $ppnRate / 100);
            $total = $this->roundMoney($dpp + $ppn);
        }

        return [
            'dpp' => $dpp,
            'ppn_rate' => $ppnRate,
            'ppn' => $ppn,
            'total' => $total,
        ];
    }

    public function calculatePph23(float $dpp, float $rate = 2): array
    {
        $pph23 = $this->roundMoney($dpp * $rate / 100);

        return [
            'dpp' => $this->roundMoney($dpp),
            'pph23_rate' => $rate,
            'pph23' => $pph23,
            'net' => $this->roundMoney($dpp - $pph23),
        ];
    }

    protected function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}