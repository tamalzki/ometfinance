<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\Project;
use App\Models\Voucher;
use App\Models\VoucherPayment;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportVouchers extends Command
{
    protected $signature = 'vouchers:import
                            {--fresh : Delete all existing vouchers (and reverse their ledger entries) before importing}';

    protected $description = 'Import vouchers from Onemark-Davao-Daily-Issuances-2026.xlsx';

    // ── Type map ──────────────────────────────────────────────────────────
    private const TYPE_MAP = [
        'COLLECTION'    => 'collection',
        'CASH ADVANCE'  => 'cash_advance',
        'PAYROLL'       => 'payroll',
        'ENCASHMENT'    => 'encashment',
        'FUND TRANSFER' => 'transfer',
        'TRF'           => 'transfer',
        'TELEX'         => 'fund_transfer',
        'PROF FEE'      => 'prof_fee',
        'REIM'          => 'reimbursement',
        'REPLE'         => 'replenishment',
        'RENT'          => 'rent',
        'WITHDRAWAL'    => 'encashment',
        'FINAL PAY'     => 'payroll',
        'MANDATORIES'   => 'other',
        'RETENTION'     => 'other',
        'HOUSEHOLD'     => 'other',
        'OPEX'          => 'other',
        'BUS. PERMIT'   => 'other',
        'PCF'           => 'replenishment',
        'RFP'           => 'rfp',
        'FUEL'          => 'rfp',
        'PMS'           => 'rfp',
        'MOBILIZATION'  => 'rfp',
    ];

    // ── Mode map ─────────────────────────────────────────────────────────
    private const MODE_MAP = [
        'cash'             => 'cash',
        'check'            => 'check',
        'cheque'           => 'check',
        'autodebit'        => 'autodebit',
        'auto debit'       => 'autodebit',
        'fund transfer'    => 'fund_transfer',
        'gcash'            => 'gcash',
        'lddap-ada'        => 'lddap_ada',
        "manager's check"  => 'managers_check',
        'managers check'   => 'managers_check',
        'cash deposit'     => 'cash_deposit',
    ];

    // ── Bank-name → DB bank_account_id ────────────────────────────────────
    // Names taken from the Excel's Source Bank column (col 19).
    private const BANK_MAP = [
        'LBP'                           => 4,   // LandBank | Onemark
        'LBP OMET'                      => 4,
        'LBP PHIC'                      => 4,
        'PNB Savings OMET'              => 6,   // PNB Savings | Onemark
        'PNB Checking OMET'             => 7,   // PNB Checking | Onemark
        'PNB - Angel'                   => 7,
        'PNB Diversion'                 => 8,   // PNB Diversion | Onemark
        'PNB MRJ AND SPJ'               => 23,  // PNB Joint
        'PNB MRJ OR SPJ'                => 23,
        'SECBANK OMET'                  => 9,   // SecBank Checking | Onemark
        'Secbank MRJ'                   => 21,  // Secbank | Personal MRJ
        'BPI Savings'                   => 10,  // BPI Savings | Onemark
        'BPI Ateneo/Azuela'             => 11,  // BPI Checking | Onemark
        'BPI Rivera OMET'               => 11,
        'BPI Rivera'                    => 11,
        'Chinabank Monte OMET'          => 12,  // Chinabank Checking | Onemark
        'ChinaBank Monte'               => 12,
        'Sterling OMET'                 => 1,   // Sterling Checking | Onemark
        'Sterling'                      => 1,
        'Sterling Corange'              => 13,  // Sterling | Corange
        'Sterling MRJ OR SPJ'           => 22,  // Sterling Joint
        'BDO BGC OMET'                  => 3,   // BDO Checking | Onemark
        'BDO BGC'                       => 3,
        'BDO OMET BGC'                  => 3,
        'BDO GDY'                       => 3,
        'BDO Corange'                   => 14,  // BDO | Corange
        'DBP OMET'                      => 5,   // DBP Checking | Onemark
        'DBP'                           => 5,
        'PNB Checking'                  => 7,
    ];

    public function handle(): int
    {
        $path = base_path('Onemark-Davao-Daily-Issuances-2026.xlsx');

        if (! file_exists($path)) {
            $this->error("Excel file not found at: {$path}");
            return 1;
        }

        // ── Fresh-start: reverse all sample payments & delete vouchers ────
        if ($this->option('fresh')) {
            $this->info('Reversing existing voucher payments and deleting vouchers…');
            Voucher::with(['payments.ledgerEntry', 'payments.projectExpense'])
                ->get()
                ->each(fn ($v) => VoucherService::destroyVoucher($v));
            Voucher::withTrashed()->forceDelete();
            $this->info('Done — slate is clean.');
        }

        // ── Load Excel ────────────────────────────────────────────────────
        $this->info('Loading Excel file…');
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestRow();
        $this->info("Rows in sheet: {$maxRow}  (headers on row 9, data from row 10)");

        // ── Build lookup caches ───────────────────────────────────────────
        $projectMap  = Project::all()->mapWithKeys(
            fn ($p) => [strtolower(trim($p->name)) => $p->id]
        )->toArray();

        $existing = Voucher::withTrashed()->pluck('voucher_no')->flip()->toArray();

        // ── Iterate rows ──────────────────────────────────────────────────
        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar($maxRow - 9);
        $bar->start();

        for ($row = 10; $row <= $maxRow; $row++) {
            $bar->advance();

            // Read all columns
            $cvDate      = $sheet->getCellByColumnAndRow(1,  $row)->getValue();
            $cvNo        = trim((string) $sheet->getCellByColumnAndRow(2,  $row)->getValue());
            $poNo        = trim((string) $sheet->getCellByColumnAndRow(3,  $row)->getValue());
            $refNo       = trim((string) $sheet->getCellByColumnAndRow(4,  $row)->getValue());
            $payee       = trim((string) $sheet->getCellByColumnAndRow(5,  $row)->getValue());
            $amtPayable  = $sheet->getCellByColumnAndRow(6,  $row)->getCalculatedValue();
            $projectName = trim((string) $sheet->getCellByColumnAndRow(7,  $row)->getValue());
            // col 8  = Accounts Title   (double-entry sub-row indicator — skip)
            // cols 9-11 = Debit/Credit/Net  (derived, skip)
            $amtPaid     = $sheet->getCellByColumnAndRow(12, $row)->getCalculatedValue();
            $status      = trim((string) $sheet->getCellByColumnAndRow(13, $row)->getValue());
            $particular  = trim((string) $sheet->getCellByColumnAndRow(14, $row)->getValue());
            $remarks     = trim((string) $sheet->getCellByColumnAndRow(15, $row)->getValue());
            $modeRaw     = trim((string) $sheet->getCellByColumnAndRow(16, $row)->getValue());
            $checkNo     = trim((string) $sheet->getCellByColumnAndRow(17, $row)->getValue());
            $checkDate   = trim((string) $sheet->getCellByColumnAndRow(18, $row)->getValue());
            $sourceBank  = trim((string) $sheet->getCellByColumnAndRow(19, $row)->getValue());
            $sourceFund  = trim((string) $sheet->getCellByColumnAndRow(20, $row)->getValue());
            $orRef       = trim((string) $sheet->getCellByColumnAndRow(21, $row)->getValue());
            $change      = $sheet->getCellByColumnAndRow(22, $row)->getCalculatedValue();

            // Only import primary rows (have CV number + amount payable)
            if ($cvNo === '' || $amtPayable === null || $amtPayable === '') {
                continue;
            }
            if ((float) $amtPayable <= 0) {
                continue;
            }

            // Skip duplicates
            if (isset($existing[$cvNo])) {
                $skipped++;
                continue;
            }

            // ── Date parsing ─────────────────────────────────────────────
            $voucherDate = $this->parseExcelDate($cvDate);
            if (! $voucherDate) {
                continue; // can't import without a date
            }

            // ── Field mapping ─────────────────────────────────────────────
            $txType    = $this->mapType($poNo);
            $reference = $refNo !== '' ? $refNo
                : (preg_match('/^PO\d+/i', $poNo) ? $poNo : null);

            $mappedStatus = $this->mapStatus($status);
            $modeKey      = $this->mapMode($modeRaw, $checkNo);
            $projectId    = $this->matchProject($projectName, $projectMap);
            $bankId       = self::BANK_MAP[$sourceBank] ?? null;

            $checkDateParsed = $this->parseFlexDate($checkDate);

            // ── Create voucher ────────────────────────────────────────────
            $voucher = Voucher::create([
                'voucher_no'             => $cvNo,
                'voucher_date'           => $voucherDate->format('Y-m-d'),
                'due_date'               => null,
                'release_date'           => null,
                'payee_name'             => $payee,
                'project_id'             => $projectId,
                'source_bank_account_id' => $bankId,
                'transaction_type'       => $txType,
                'reference'              => $reference,
                'amount_payable'         => (float) $amtPayable,
                'mode_of_payment'        => $modeKey,
                'status'                 => 'unpaid',
                'particular'             => $particular !== '' ? $particular : null,
                'notes'                  => null,
                'remarks'                => $remarks !== '' ? $remarks : null,
                'source_of_fund'         => $sourceFund !== '' ? $sourceFund : null,
                'or_ref'                 => $orRef !== '' ? $orRef : null,
                'change_amount'          => ($change !== null && (float) $change > 0) ? (float) $change : null,
            ]);

            // ── Create payment record (no ledger posting for historical data)
            $amtPaidFloat = (float) $amtPaid;
            if ($amtPaidFloat > 0 && ! in_array($mappedStatus, ['cancelled', 'unpaid'], true)) {
                $isCheck  = in_array($modeKey, ['check', 'managers_check'], true);
                $paidOn   = $checkDateParsed ?? $voucherDate;

                VoucherPayment::create([
                    'voucher_id'         => $voucher->id,
                    'bank_account_id'    => $bankId,
                    'ledger_entry_id'    => null,   // historical — no bank debit posted
                    'project_expense_id' => null,
                    'paid_on'            => $paidOn->format('Y-m-d'),
                    'amount'             => $amtPaidFloat,
                    'mode'               => $modeKey,
                    'check_no'           => $isCheck && $checkNo !== '' && strtolower($checkNo) !== 'cash'
                                              ? $checkNo : null,
                    'check_date'         => $isCheck ? $checkDateParsed?->format('Y-m-d') : null,
                    'notes'              => null,
                ]);
            }

            // ── Set final status ─────────────────────────────────────────
            if ($mappedStatus === 'cancelled') {
                $voucher->update(['status' => 'cancelled']);
            } else {
                VoucherService::recompute($voucher->fresh(['payments']));
            }

            $existing[$cvNo] = true;
            $imported++;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Import complete — {$imported} vouchers imported, {$skipped} skipped (already existed).");

        return 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function parseExcelDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {}
        return null;
    }

    private function parseFlexDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = (string) $value;
        // Format used in the sheet: MM.DD.YYYY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $str, $m)) {
            try {
                return Carbon::create((int) $m[3], (int) $m[1], (int) $m[2]);
            } catch (\Throwable) {}
        }
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse($str);
        } catch (\Throwable) {}
        return null;
    }

    private function mapType(string $raw): string
    {
        $upper = strtoupper($raw);
        if (isset(self::TYPE_MAP[$upper])) {
            return self::TYPE_MAP[$upper];
        }
        // FUND #01, FUND #02, … FUND TRANSFER
        if (str_starts_with($upper, 'FUND ')) {
            return 'encashment';
        }
        // PO numbers (PO6132, etc.)
        if (preg_match('/^PO\d+/i', $raw)) {
            return 'rfp';
        }
        // Billing codes, contract codes, etc. → rfp
        return 'rfp';
    }

    private function mapMode(string $raw, string $checkNo): ?string
    {
        $lower = strtolower($raw);
        if ($lower !== '' && isset(self::MODE_MAP[$lower])) {
            return self::MODE_MAP[$lower];
        }
        // Some rows have "Cash" mis-entered in the Check No. column
        if ($lower === '' && strtolower($checkNo) === 'cash') {
            return 'cash';
        }
        return $lower !== '' ? 'other' : null;
    }

    private function mapStatus(string $raw): string
    {
        return match (strtoupper($raw)) {
            'PAID'           => 'paid',
            'CANCELLED'      => 'cancelled',
            'UNPAID'         => 'unpaid',
            'PDC'            => 'pdc',
            'PARTIALLY PAID' => 'partial',
            default          => 'unpaid',
        };
    }

    private function matchProject(string $name, array $projectMap): ?int
    {
        if ($name === '') {
            return null;
        }
        $lower = strtolower($name);

        // Exact match first
        if (isset($projectMap[$lower])) {
            return $projectMap[$lower];
        }
        // Substring match (e.g. "Admin - Villa Josefina" → "josefina")
        foreach ($projectMap as $dbName => $id) {
            if (str_contains($lower, $dbName) || str_contains($dbName, $lower)) {
                return $id;
            }
        }
        return null;
    }
}
