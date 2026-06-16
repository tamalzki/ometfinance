<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Voucher;
use App\Support\DailyTransactionProjectResolver;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncProjectsFromExcel extends Command
{
    protected $signature = 'projects:sync-from-excel';

    protected $description = 'Create projects from the Excel "Project" column (names containing "Admin" = in-house, others = external) and re-link voucher project_id values';

    public function handle(): int
    {
        $path = base_path('Onemark-Davao-Daily-Issuances-2026.xlsx');

        if (! file_exists($path)) {
            $this->error("Excel file not found at: {$path}");
            return 1;
        }

        $this->info('Loading Excel file…');
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestRow();

        // ── Pass 1: collect canonical project name per voucher row ────────
        $rowProjects = []; // voucher_no => canonical name
        $names       = []; // canonical name => true

        for ($row = 10; $row <= $maxRow; $row++) {
            $cvNo       = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getValue());
            $amtPayable = $sheet->getCellByColumnAndRow(6, $row)->getCalculatedValue();
            if ($cvNo === '' || $amtPayable === null || $amtPayable === '' || (float) $amtPayable <= 0) {
                continue;
            }

            $canonical = DailyTransactionProjectResolver::canonicalName(
                trim((string) $sheet->getCellByColumnAndRow(7, $row)->getValue())
            );

            if ($canonical === null) {
                continue;
            }

            $rowProjects[$cvNo] = $canonical;
            $names[$canonical]  = true;
        }

        DailyTransactionProjectResolver::forgetCache();
        $existingByName = DailyTransactionProjectResolver::projectMap(true);

        // ── Create missing projects, classified by "Admin" keyword ────────
        $created = 0;
        foreach (array_keys($names) as $name) {
            $key = DailyTransactionProjectResolver::normalizeKey($name);

            if ($key === null || isset($existingByName[$key])) {
                continue;
            }

            $isInHouse = str_contains($key, 'admin');

            $project = Project::create([
                'name'           => $name,
                'kind'           => $isInHouse ? 'in_house' : 'external',
                'status'         => 'active',
                'client_name'    => $isInHouse ? 'Onemark (internal)' : $name,
                'contract_value' => 0,
            ]);

            $existingByName[$key] = $project->id;
            $created++;
        }

        DailyTransactionProjectResolver::forgetCache();
        $this->info("Created {$created} new project(s).");

        // ── Re-link voucher project_id from canonical names ────────────────
        $updated = 0;
        $projectMap = DailyTransactionProjectResolver::projectMap(true);

        foreach ($rowProjects as $voucherNo => $canonical) {
            $key       = DailyTransactionProjectResolver::normalizeKey($canonical);
            $projectId = $key ? ($projectMap[$key] ?? null) : null;

            if ($projectId === null) {
                continue;
            }

            $updated += Voucher::where('voucher_no', $voucherNo)
                ->where(function ($q) use ($projectId) {
                    $q->whereNull('project_id')->orWhere('project_id', '!=', $projectId);
                })
                ->update(['project_id' => $projectId]);
        }

        $this->info("Re-linked {$updated} voucher(s) to projects.");

        return 0;
    }
}
