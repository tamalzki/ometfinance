<?php

namespace App\Support;

/**
 * Statutory/contractual deductions on external project collections.
 *
 * Government billing (Excel reference): gross is VAT-inclusive, so VAT and WHT
 * are taken on the VAT-exclusive base (gross ÷ 1.12). Retention and
 * recoupment apply to the gross amount. Each line is rounded to 2 decimals.
 */
final class CollectionDeductionCalculator
{
    public const VAT_INCLUSIVE_FACTOR = 1.12;

    /**
     * @param  array{vat_rate?: float|int|string|null, wht_rate?: float|int|string|null, retention_rate?: float|int|string|null, recoupment_rate?: float|int|string|null}  $rates
     * @return array{vat_amount: float, wht_amount: float, retention_amount: float, recoupment_amount: float, other_deductions_amount: float}
     */
    public static function amounts(float $gross, bool $isGovernment, array $rates, float $otherDeductions = 0): array
    {
        $vatWhtBase = $isGovernment ? $gross / self::VAT_INCLUSIVE_FACTOR : $gross;

        return [
            'vat_amount'              => round($vatWhtBase * (float) ($rates['vat_rate'] ?? 0) / 100, 2),
            'wht_amount'              => round($vatWhtBase * (float) ($rates['wht_rate'] ?? 0) / 100, 2),
            'retention_amount'        => round($gross * (float) ($rates['retention_rate'] ?? 0) / 100, 2),
            'recoupment_amount'       => round($gross * (float) ($rates['recoupment_rate'] ?? 0) / 100, 2),
            'other_deductions_amount' => round($otherDeductions, 2),
        ];
    }

    /** @param  array{vat_amount: float, wht_amount: float, retention_amount: float, recoupment_amount: float, other_deductions_amount: float}  $amounts */
    public static function total(array $amounts): float
    {
        return $amounts['vat_amount']
            + $amounts['wht_amount']
            + $amounts['retention_amount']
            + $amounts['recoupment_amount']
            + $amounts['other_deductions_amount'];
    }

    public static function net(float $gross, array $amounts): float
    {
        return $gross - self::total($amounts);
    }
}
