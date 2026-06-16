<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use App\Support\DailyTransactionProjectResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LinkVoucherProjects extends Command
{
    protected $signature = 'vouchers:link-projects
                            {--file= : Path to daily_transactions_2026.json (defaults to database/seeders/data/daily_transactions_2026.json)}
                            {--excel : Read project names from Onemark-Davao-Daily-Issuances-2026.xlsx instead of JSON}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Link imported daily-transaction vouchers to existing projects by voucher number (production-safe — only updates project_id)';

    public function handle(): int
    {
        $pairs = $this->option('excel')
            ? $this->pairsFromExcel()
            : $this->pairsFromJson($this->option('file'));

        if ($pairs === null) {
            return self::FAILURE;
        }

        DailyTransactionProjectResolver::forgetCache();
        $map = DailyTransactionProjectResolver::projectMap(true);

        $updated   = 0;
        $already   = 0;
        $missing   = 0;
        $noProject = 0;
        $dryRun    = (bool) $this->option('dry-run');

        foreach ($pairs as $voucherNo => $projectName) {
            $projectId = DailyTransactionProjectResolver::resolveId($projectName);

            if ($projectId === null) {
                $noProject++;
                $this->warn("No project match for {$voucherNo}: \"{$projectName}\"");

                continue;
            }

            $voucher = Voucher::where('voucher_no', $voucherNo)->first();

            if (! $voucher) {
                $missing++;

                continue;
            }

            if ((int) $voucher->project_id === (int) $projectId) {
                $already++;

                continue;
            }

            if ($dryRun) {
                $this->line("Would link {$voucherNo} → {$projectName} (id {$projectId})");
                $updated++;

                continue;
            }

            $voucher->update(['project_id' => $projectId]);
            $updated++;
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Project linking complete.');
        $this->line("  Updated   : {$updated}");
        $this->line("  Unchanged : {$already}");
        $this->line("  Voucher not found : {$missing}");
        $this->line('  Project name unmatched : ' . $noProject);
        $this->line('  Projects in database   : ' . count($map));

        if ($noProject > 0) {
            $this->newLine();
            $this->comment('Run projects:sync-from-excel first to create missing projects from the spreadsheet.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>|null voucher_no => canonical project name
     */
    private function pairsFromJson(?string $file): ?array
    {
        $path = $file
            ? (str_starts_with($file, '/') ? $file : base_path($file))
            : database_path('seeders/data/daily_transactions_2026.json');

        if (! File::exists($path)) {
            $this->error("JSON not found: {$path}");
            $this->line('Use --excel or run: php artisan vouchers:export-daily-transactions-data');

            return null;
        }

        $payload = json_decode(File::get($path), true);
        $pairs   = [];

        foreach ($payload['vouchers'] ?? [] as $row) {
            $no   = trim((string) ($row['voucher_no'] ?? ''));
            $name = DailyTransactionProjectResolver::canonicalName($row['project_name'] ?? null);

            if ($no !== '' && $name !== null) {
                $pairs[$no] = $name;
            }
        }

        $this->info('Loaded ' . count($pairs) . ' voucher → project pairs from JSON.');

        return $pairs;
    }

    /**
     * @return array<string, string>|null
     */
    private function pairsFromExcel(): ?array
    {
        $path = base_path('Onemark-Davao-Daily-Issuances-2026.xlsx');

        if (! file_exists($path)) {
            $this->error("Excel file not found: {$path}");

            return null;
        }

        $sheet  = IOFactory::load($path)->getActiveSheet();
        $maxRow = $sheet->getHighestRow();
        $pairs  = [];

        for ($row = 10; $row <= $maxRow; $row++) {
            $cvNo       = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getCalculatedValue());
            $amtPayable = $sheet->getCellByColumnAndRow(6, $row)->getCalculatedValue();

            if ($cvNo === '' || $amtPayable === null || $amtPayable === '' || (float) $amtPayable <= 0) {
                continue;
            }

            $name = DailyTransactionProjectResolver::canonicalName(
                trim((string) $sheet->getCellByColumnAndRow(7, $row)->getCalculatedValue())
            );

            if ($name !== null) {
                $pairs[$cvNo] = $name;
            }
        }

        $this->info('Loaded ' . count($pairs) . ' voucher → project pairs from Excel.');

        return $pairs;
    }
}
