<?php

namespace App\Console\Commands;

use App\Models\Payee;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncPayeesFromExcel extends Command
{
    protected $signature = 'payees:sync-from-excel';

    protected $description = 'Create payees from the distinct names found in the Excel "Payee" column';

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

        $names = [];
        for ($row = 10; $row <= $maxRow; $row++) {
            $payee = trim((string) $sheet->getCellByColumnAndRow(5, $row)->getValue());
            if ($payee === '') {
                continue;
            }
            $names[$payee] = true;
        }

        $existing = Payee::pluck('name')->mapWithKeys(fn ($n) => [strtolower($n) => true])->toArray();

        $created = 0;
        foreach (array_keys($names) as $name) {
            if (isset($existing[strtolower($name)])) {
                continue;
            }
            Payee::create(['name' => $name]);
            $existing[strtolower($name)] = true;
            $created++;
        }

        $this->info("Created {$created} new payee(s).");

        return 0;
    }
}
