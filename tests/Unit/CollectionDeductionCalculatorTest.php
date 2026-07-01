<?php

namespace Tests\Unit;

use App\Support\CollectionDeductionCalculator;
use PHPUnit\Framework\TestCase;

class CollectionDeductionCalculatorTest extends TestCase
{
    public function test_government_deductions_match_excel_sample_row(): void
    {
        // Sample-Project-Computation.xlsx · 20.01% billing (row 12)
        $gross = 179688888.88 * 0.2001;

        $amounts = CollectionDeductionCalculator::amounts($gross, true, [
            'vat_rate'        => 5,
            'wht_rate'        => 2,
            'retention_rate'  => 10,
            'recoupment_rate' => 15,
        ]);

        $this->assertSame(1605167.26, $amounts['vat_amount']);
        $this->assertSame(642066.9, $amounts['wht_amount']);
        $this->assertSame(3595574.67, $amounts['retention_amount']);
        $this->assertSame(5393362.0, $amounts['recoupment_amount']);
        $this->assertEqualsWithDelta(24719575.834888, CollectionDeductionCalculator::net($gross, $amounts), 0.000001);
    }

    public function test_private_deductions_use_gross_for_vat_and_wht(): void
    {
        $amounts = CollectionDeductionCalculator::amounts(100000, false, [
            'vat_rate' => 5,
            'wht_rate' => 2,
        ]);

        $this->assertSame(5000.0, $amounts['vat_amount']);
        $this->assertSame(2000.0, $amounts['wht_amount']);
    }
}
