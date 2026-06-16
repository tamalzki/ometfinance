<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExportDailyTransactionsSeedData extends Command
{
    protected $signature = 'vouchers:export-daily-transactions-data
                            {file=Onemark-Davao-Daily-Issuances-2026.xlsx : Path to the Excel workbook (relative to project root)}';

    protected $description = 'Export the Daily Transactions sheet to database/seeders/data/daily_transactions_2026.json';

    /** @var array<string, string> */
    private array $typeMap = [
        'RFP' => 'rfp', 'PAYROLL' => 'payroll', 'ENCASHMENT' => 'encashment',
        'FUND TRANSFER' => 'transfer', 'TRF' => 'transfer', 'PROF FEE' => 'prof_fee',
        'CASH ADVANCE' => 'cash_advance', 'REIM' => 'reimbursement', 'REPLE' => 'replenishment',
        'RENT' => 'rent', 'COLLECTION' => 'collection', 'TELEX' => 'other',
    ];

    /** @var array<string, string> */
    private array $modeMap = [
        'CASH' => 'cash', 'CHECK' => 'check', 'FUND TRANSFER' => 'fund_transfer',
        'GCASH' => 'gcash', 'AUTODEBIT' => 'autodebit', 'CASH DEPOSIT' => 'cash_deposit',
        'LDDAP-ADA' => 'lddap_ada', "MANAGER'S CHECK" => 'managers_check',
        'PDC' => 'check', 'PCF' => 'cash', 'GCASH BILLS PAYMENT' => 'gcash', 'FUNDED BY SPJ' => 'other',
    ];

    /** @var array<string, string> */
    private array $statusMap = [
        'PAID' => 'paid', 'Paid' => 'paid', 'UNPAID' => 'unpaid',
        'PARTIALLY PAID' => 'partial', 'PDC' => 'pdc', 'CANCELLED' => 'cancelled',
    ];

    /** @var array<string, string|null> */
    private array $bankMap = [
        'STERLING OMET' => 'Sterling Checking', 'STERLING CORANGE' => 'Sterling',
        'STERLING MRJ OR SPJ' => 'Sterling', 'PNB SAVINGS OMET' => 'PNB Savings',
        'PNB CHECKING OMET' => 'PNB Checking', 'PNB DIVERSION OMET' => 'PNB Diversion',
        'PNB MRJ OR SPJ' => 'PNB 1', 'PNB MRJ AND SPJ' => 'PNB 1', 'PNB - ANGEL' => 'PNB 1',
        'BDO BGC' => 'BDO Checking', 'BDO BGC OMET' => 'BDO Checking', 'BDO OMET BGC' => 'BDO Checking',
        'BDO GDY' => 'BDO Checking', 'BDO CORANGE' => 'BDO', 'BPI RIVERA OMET' => 'BPI Checking',
        'BPI ATENEO/AZUELA' => 'BPI Checking', 'BPI SAVINGS OMET' => 'BPI Savings',
        'SECBANK OMET' => 'SecBank Checking', 'SECBANK MRJ' => 'Secbank',
        'DBP OMET' => 'DBP Checking', 'DBP' => 'DBP Checking', 'LBP' => 'LandBank',
        'LBP OMET' => 'LandBank', 'LBP PHIC' => 'LandBank', 'CHINABANK OMET' => 'Chinabank Checking',
        'CHINABANK MONTE OMET' => 'Chinabank Checking', 'CHINABANK MONTE' => 'Chinabank Checking',
        'MAYBANK RR/OMET JV' => 'BPI Checking', 'CASH ON HAND' => null, 'PCF' => null,
        'SPJ' => null, 'RCJ' => null, 'OPEX' => null, 'MTBC' => null,
    ];

    public function handle(): int
    {
        $path = base_path($this->argument('file'));

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $sheet = IOFactory::load($path)->getSheetByName('2026 DAILY TRANSACTIONS')
            ?? IOFactory::load($path)->getActiveSheet();

        $rows = [];
        $max  = $sheet->getHighestRow();

        for ($r = 10; $r <= $max; $r++) {
            $cv = $sheet->getCellByColumnAndRow(2, $r)->getCalculatedValue();
            if (! $cv) {
                continue;
            }

            $rows[] = [
                'cv_number'      => trim((string) $cv),
                'cv_date'        => $this->parseDate($sheet->getCellByColumnAndRow(1, $r)->getCalculatedValue()),
                'po_number'      => $sheet->getCellByColumnAndRow(3, $r)->getCalculatedValue(),
                'pr_ref'         => $sheet->getCellByColumnAndRow(4, $r)->getCalculatedValue(),
                'payee'          => $sheet->getCellByColumnAndRow(5, $r)->getCalculatedValue(),
                'amount_payable' => $this->toFloat($sheet->getCellByColumnAndRow(6, $r)->getCalculatedValue()),
                'project'        => $sheet->getCellByColumnAndRow(7, $r)->getCalculatedValue(),
                'accounts_title' => $sheet->getCellByColumnAndRow(8, $r)->getCalculatedValue(),
                'debit'          => $this->toFloat($sheet->getCellByColumnAndRow(9, $r)->getCalculatedValue()),
                'amount_paid'    => $this->toFloat($sheet->getCellByColumnAndRow(12, $r)->getCalculatedValue()),
                'status'         => $sheet->getCellByColumnAndRow(13, $r)->getCalculatedValue(),
                'particular'     => $sheet->getCellByColumnAndRow(14, $r)->getCalculatedValue(),
                'remarks'        => $sheet->getCellByColumnAndRow(15, $r)->getCalculatedValue(),
                'mode'           => $sheet->getCellByColumnAndRow(16, $r)->getCalculatedValue(),
                'check_no'       => $sheet->getCellByColumnAndRow(17, $r)->getCalculatedValue(),
                'check_date'     => $this->parseDate($sheet->getCellByColumnAndRow(18, $r)->getCalculatedValue()),
                'source_bank'    => $sheet->getCellByColumnAndRow(19, $r)->getCalculatedValue(),
                'source_of_fund' => $sheet->getCellByColumnAndRow(20, $r)->getCalculatedValue(),
                'or_ref'         => $sheet->getCellByColumnAndRow(21, $r)->getCalculatedValue(),
                'change_amount'  => $this->toFloat($sheet->getCellByColumnAndRow(22, $r)->getCalculatedValue()),
                'notes'          => $sheet->getCellByColumnAndRow(23, $r)->getCalculatedValue(),
            ];
        }

        $byCv = [];
        foreach ($rows as $row) {
            $byCv[$row['cv_number']][] = $row;
        }

        $vouchers = [];
        $skipped  = [];

        foreach ($byCv as $cv => $lines) {
            $primary = collect($lines)->first(fn ($ln) => $ln['amount_payable'] || $ln['status']) ?? $lines[0];
            $amountPayable = $primary['amount_payable'];
            $amountPaid    = $primary['amount_paid'] ?? 0.0;

            if (! $amountPayable) {
                $amountPayable = collect($lines)->max('debit');
            }

            if (! $amountPayable || $amountPayable <= 0) {
                $skipped[] = ['voucher_no' => $cv, 'reason' => 'missing_amount'];
                continue;
            }

            if (! $primary['payee'] || ! trim((string) $primary['payee'])) {
                $skipped[] = ['voucher_no' => $cv, 'reason' => 'missing_payee'];
                continue;
            }

            $rawStatus = $primary['status'];
            $status    = $this->statusMap[trim((string) $rawStatus)] ?? null;

            if (! $status) {
                if ($amountPaid >= $amountPayable - 0.01) {
                    $status = 'paid';
                } elseif ($amountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'unpaid';
                }
            }

            $cvDate    = $primary['cv_date'] ?? '2026-01-01';
            $checkDate = $primary['check_date'];

            $vouchers[] = [
                'voucher_no'         => $cv,
                'voucher_date'       => $cvDate,
                'due_date'           => null,
                'release_date'       => $checkDate ?? ($status === 'paid' ? $cvDate : null),
                'payee_name'         => trim((string) $primary['payee']),
                'project_name'       => $primary['project'] ? trim((string) $primary['project']) : null,
                'source_bank_label'  => $this->mapBank($primary['source_bank']),
                'transaction_type'   => $this->mapType($primary['po_number']),
                'po_number'          => $primary['po_number'] ? trim((string) $primary['po_number']) : null,
                'reference'          => $primary['pr_ref'] ? trim((string) $primary['pr_ref']) : null,
                'amount_payable'     => $amountPayable,
                'amount_paid'        => $amountPaid,
                'mode_of_payment'    => $this->mapMode($primary['mode']),
                'status'             => $status,
                'particular'         => $primary['particular'] ? trim((string) $primary['particular']) : null,
                'remarks'            => $primary['remarks'] ? trim((string) $primary['remarks']) : null,
                'source_of_fund'     => $primary['source_of_fund'] ? trim((string) $primary['source_of_fund']) : null,
                'or_ref'             => $primary['or_ref'] ? trim((string) $primary['or_ref']) : null,
                'change_amount'      => $primary['change_amount'],
                'notes'              => $primary['notes'] ? trim((string) $primary['notes']) : null,
                'check_no'           => $primary['check_no'] ? trim((string) $primary['check_no']) : null,
                'check_date'         => $checkDate,
                'accounts_title'     => $primary['accounts_title'] ? trim((string) $primary['accounts_title']) : null,
                'journal_lines'      => count($lines),
            ];
        }

        $outDir = database_path('seeders/data');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $outPath = $outDir . '/daily_transactions_2026.json';
        $payload = [
            'meta' => [
                'source_file'    => basename($path),
                'sheet'          => '2026 DAILY TRANSACTIONS',
                'header_row'     => 9,
                'data_row_start' => 10,
                'generated_at'   => now()->toIso8601String(),
                'voucher_count'  => count($vouchers),
                'skipped'        => $skipped,
            ],
            'vouchers' => $vouchers,
        ];

        file_put_contents($outPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("Exported " . count($vouchers) . " vouchers to {$outPath}");
        $this->line('Skipped ' . count($skipped) . ' incomplete Excel rows.');

        return self::SUCCESS;
    }

    /** @param mixed $value */
    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (preg_match('/^([\d,.]+)/', (string) $value, $m)) {
            return round((float) str_replace(',', '', $m[1]), 2);
        }

        return null;
    }

    /** @param mixed $value */
    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $s = trim((string) $value);
        foreach (['m.d.Y', 'm/d/Y', 'Y-m-d', 'd.m.Y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $s);
            if ($dt) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /** @param mixed $po */
    private function mapType($po): string
    {
        $key = strtoupper(trim((string) ($po ?? '')));
        if ($key === '') {
            return 'other';
        }
        if (isset($this->typeMap[$key])) {
            return $this->typeMap[$key];
        }
        foreach ($this->typeMap as $prefix => $type) {
            if (str_starts_with($key, $prefix)) {
                return $type;
            }
        }

        return 'other';
    }

    /** @param mixed $mode */
    private function mapMode($mode): ?string
    {
        if (! $mode) {
            return null;
        }

        $key = strtoupper(trim((string) $mode));

        return $this->modeMap[$key] ?? 'other';
    }

    /** @param mixed $bank */
    private function mapBank($bank): ?string
    {
        if (! $bank) {
            return null;
        }

        $key = strtoupper(trim((string) $bank));

        if (str_starts_with($key, 'FROM ')) {
            return null;
        }

        if (array_key_exists($key, $this->bankMap)) {
            return $this->bankMap[$key];
        }

        foreach ($this->bankMap as $alias => $target) {
            if (str_contains($key, $alias)) {
                return $target;
            }
        }

        return trim((string) $bank);
    }
}
