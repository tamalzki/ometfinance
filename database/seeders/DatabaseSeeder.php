<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Database\Seeders\AccountSeeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $projects = [
            // ── In-house ──────────────────────────────────────────────────────
            [
                'name'           => 'Croc Park',
                'kind'           => 'in_house',
                'code'           => 'CP-001',
                'client_name'    => 'Onemark (internal)',
                'location'       => 'Davao',
                'status'         => 'active',
                'contract_value' => 0,
                'start_date'     => null,
                'end_date'       => null,
                'due_date'       => null,
            ],
            [
                'name'           => 'Josefina',
                'kind'           => 'in_house',
                'code'           => 'JF-001',
                'client_name'    => 'Onemark (internal)',
                'location'       => 'Davao',
                'status'         => 'active',
                'contract_value' => 0,
                'start_date'     => null,
                'end_date'       => null,
                'due_date'       => null,
            ],

            // ── External ──────────────────────────────────────────────────────
            [
                'name'           => 'APMC Project',
                'kind'           => 'external',
                'code'           => 'APMC-001',
                'client_name'    => 'APMC',
                'location'       => 'Davao City',
                'status'         => 'active',
                'contract_value' => 0,
                'start_date'     => null,
                'end_date'       => null,
                'due_date'       => null,
            ],
            [
                'name'           => 'ESCO Project',
                'kind'           => 'external',
                'code'           => 'ESCO-001',
                'client_name'    => 'ESCO',
                'location'       => 'Davao City',
                'status'         => 'active',
                'contract_value' => 0,
                'start_date'     => null,
                'end_date'       => null,
                'due_date'       => null,
            ],
            [
                'name'           => 'AMOMC Project',
                'kind'           => 'external',
                'code'           => 'AMOMC-001',
                'client_name'    => 'AMOMC',
                'location'       => 'Davao City',
                'status'         => 'active',
                'contract_value' => 0,
                'start_date'     => null,
                'end_date'       => null,
                'due_date'       => null,
            ],
        ];

        foreach ($projects as $project) {
            Project::updateOrCreate(
                ['code' => $project['code']],
                $project
            );
        }

        $this->call(AccountSeeder::class);
    }
}
