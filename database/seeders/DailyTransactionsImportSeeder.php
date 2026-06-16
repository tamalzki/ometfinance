<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\ProjectCategory;
use App\Models\Voucher;
use App\Models\VoucherPayment;
use App\Support\DailyTransactionProjectResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Idempotent import of the 2026 Daily Transactions spreadsheet.
 *
 * Safe for production — never truncates, never updates existing vouchers,
 * and never posts bank ledger entries (historical register only).
 *
 * Usage:
 *   php artisan db:seed --class=DailyTransactionsImportSeeder
 *
 * Data source:
 *   database/seeders/data/daily_transactions_2026.json
 *   (generated from Onemark-Davao-Daily-Issuances-2026.xlsx)
 */
class DailyTransactionsImportSeeder extends Seeder
{
    /** @var array<string, string|null> */
    private array $bankAliases = [
        'STERLING OMET'           => 'Sterling Checking',
        'STERLING CORANGE'        => 'Sterling',
        'STERLING MRJ OR SPJ'     => 'Sterling',
        'PNB SAVINGS OMET'        => 'PNB Savings',
        'PNB CHECKING OMET'       => 'PNB Checking',
        'PNB DIVERSION OMET'      => 'PNB Diversion',
        'PNB MRJ OR SPJ'          => 'PNB 1',
        'PNB MRJ AND SPJ'         => 'PNB 1',
        'PNB - ANGEL'             => 'PNB 1',
        'BDO BGC'                 => 'BDO Checking',
        'BDO BGC OMET'            => 'BDO Checking',
        'BDO OMET BGC'            => 'BDO Checking',
        'BDO GDY'                 => 'BDO Checking',
        'BDO CORANGE'             => 'BDO',
        'BPI RIVERA OMET'         => 'BPI Checking',
        'BPI ATENEO/AZUELA'       => 'BPI Checking',
        'BPI SAVINGS OMET'        => 'BPI Savings',
        'SECBANK OMET'            => 'SecBank Checking',
        'SECBANK MRJ'             => 'Secbank',
        'DBP OMET'                => 'DBP Checking',
        'DBP'                     => 'DBP Checking',
        'LBP'                     => 'LandBank',
        'LBP OMET'                => 'LandBank',
        'LBP PHIC'                => 'LandBank',
        'CHINABANK OMET'          => 'Chinabank Checking',
        'CHINABANK MONTE OMET'    => 'Chinabank Checking',
        'CHINABANK MONTE'         => 'Chinabank Checking',
        'MAYBANK RR/OMET JV'      => 'BPI Checking',
        'CASH ON HAND'            => null,
        'PCF'                     => null,
        'SPJ'                     => null,
        'RCJ'                     => null,
        'OPEX'                    => null,
        'MTBC'                    => null,
    ];

    private ?int $defaultCategoryId = null;

    /** @var array<string, int|null> */
    private array $bankCache = [];

    public function run(): void
    {
        $path = database_path('seeders/data/daily_transactions_2026.json');

        if (! File::exists($path)) {
            $this->command?->error("Seed data not found: {$path}");
            $this->command?->line('Run: php artisan vouchers:export-daily-transactions-data');

            return;
        }

        $payload = json_decode(File::get($path), true);

        if (! is_array($payload) || ! isset($payload['vouchers']) || ! is_array($payload['vouchers'])) {
            $this->command?->error('Invalid seed data format.');

            return;
        }

        $this->defaultCategoryId = $this->resolveDefaultCategoryId();

        $inserted  = 0;
        $skipped   = 0;
        $payments  = 0;
        $errors    = 0;

        foreach ($payload['vouchers'] as $row) {
            try {
                $result = $this->importVoucher($row);
                if ($result === 'inserted') {
                    $inserted++;
                    if (($row['amount_paid'] ?? 0) > 0 && ($row['status'] ?? '') !== 'cancelled') {
                        $payments++;
                    }
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->command?->warn(sprintf(
                    'Failed %s: %s',
                    $row['voucher_no'] ?? '(unknown)',
                    $e->getMessage()
                ));
            }
        }

        $meta = $payload['meta'] ?? [];

        $this->command?->info('Daily Transactions import finished.');
        $this->command?->line("  Inserted : {$inserted}");
        $this->command?->line("  Skipped  : {$skipped} (already in database)");
        $this->command?->line("  Payments : {$payments} (historical — no ledger posting)");
        $this->command?->line('  Errors   : ' . $errors);

        if (! empty($meta['skipped'])) {
            $this->command?->line('  Excel rows skipped during export: ' . count($meta['skipped']));
        }

        $this->command?->newLine();
        $this->command?->comment('Tip: run php artisan vouchers:link-projects to link project_id on any vouchers that were imported before projects existed.');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'inserted'|'skipped'
     */
    private function importVoucher(array $row): string
    {
        $voucherNo = trim((string) ($row['voucher_no'] ?? ''));

        if ($voucherNo === '') {
            return 'skipped';
        }

        if (Voucher::withTrashed()->where('voucher_no', $voucherNo)->exists()) {
            return 'skipped';
        }

        $projectId = $this->resolveProjectId($row['project_name'] ?? null);
        $bankId    = $this->resolveBankAccountId($row['source_bank_label'] ?? null);
        $categoryId = $this->resolveCategoryId($row['accounts_title'] ?? null);

        $amountPayable = (float) ($row['amount_payable'] ?? 0);
        $amountPaid    = (float) ($row['amount_paid'] ?? 0);
        $status        = (string) ($row['status'] ?? 'unpaid');

        DB::transaction(function () use ($row, $voucherNo, $projectId, $bankId, $categoryId, $amountPayable, $amountPaid, $status) {
            $voucher = Voucher::create([
                'voucher_no'             => $voucherNo,
                'voucher_date'           => $row['voucher_date'],
                'due_date'               => $row['due_date'] ?? null,
                'release_date'           => $row['release_date'] ?? null,
                'payee_name'             => $row['payee_name'],
                'project_id'             => $projectId,
                'source_bank_account_id' => $bankId,
                'transaction_type'       => $row['transaction_type'] ?? 'other',
                'category_id'            => $categoryId,
                'po_number'              => $row['po_number'] ?? null,
                'reference'              => $row['reference'] ?? null,
                'amount_payable'         => $amountPayable,
                'mode_of_payment'        => $row['mode_of_payment'] ?? null,
                'status'                 => $status,
                'particular'             => $row['particular'] ?? null,
                'remarks'                => $row['remarks'] ?? null,
                'source_of_fund'         => $row['source_of_fund'] ?? null,
                'or_ref'                 => $row['or_ref'] ?? null,
                'change_amount'          => $row['change_amount'] ?? null,
                'notes'                  => isset($row['notes'])
                    ? Str::limit((string) $row['notes'], 2000, '')
                    : null,
            ]);

            if ($amountPaid > 0 && $status !== 'cancelled') {
                VoucherPayment::create([
                    'voucher_id'      => $voucher->id,
                    'bank_account_id' => $bankId,
                    'ledger_entry_id' => null,
                    'paid_on'         => $row['release_date'] ?? $row['voucher_date'],
                    'amount'          => min($amountPaid, $amountPayable),
                    'mode'            => $row['mode_of_payment'] ?? null,
                    'check_no'        => $row['check_no'] ?? null,
                    'check_date'      => $row['check_date'] ?? null,
                    'notes'           => 'Imported from 2026 Daily Transactions spreadsheet (historical — no ledger posting).',
                ]);
            }
        });

        return 'inserted';
    }

    private function resolveDefaultCategoryId(): int
    {
        $category = ProjectCategory::firstOrCreate(
            ['parent_id' => null, 'name' => 'Imported (Historical)'],
            ['name' => 'Imported (Historical)']
        );

        return $category->id;
    }

    private function resolveCategoryId(?string $accountsTitle): int
    {
        if ($accountsTitle) {
            $name = trim($accountsTitle);
            $existing = ProjectCategory::where('name', $name)->value('id');

            if ($existing) {
                return (int) $existing;
            }
        }

        return (int) $this->defaultCategoryId;
    }

    private function resolveProjectId(?string $name): ?int
    {
        return DailyTransactionProjectResolver::resolveId($name);
    }

    private function resolveBankAccountId(?string $label): ?int
    {
        if (! $label) {
            return null;
        }

        $normalized = Str::upper(trim($label));

        if (array_key_exists($normalized, $this->bankCache)) {
            return $this->bankCache[$normalized];
        }

        if (array_key_exists($normalized, $this->bankAliases)) {
            $alias = $this->bankAliases[$normalized];
            if ($alias === null) {
                return $this->bankCache[$normalized] = null;
            }
            $label = $alias;
        }

        $account = BankAccount::where('name', $label)->first()
            ?? BankAccount::where('name', 'like', '%' . $label . '%')->first();

        return $this->bankCache[$normalized] = $account?->id;
    }
}
