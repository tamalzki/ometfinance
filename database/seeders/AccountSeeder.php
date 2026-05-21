<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Entity;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name'       => 'Onemark',
                'slug'       => 'omet',
                'color'      => 'blue',
                'sort_order' => 1,
                'accounts'   => [
                    ['name' => 'Sterling Checking',   'bank_name' => 'Sterling Bank',  'account_number' => '582-6-000248-55'],
                    ['name' => 'Sterling PSP Savings', 'bank_name' => 'Sterling Bank', 'account_number' => null],
                    ['name' => 'BDO Checking',         'bank_name' => 'BDO',           'account_number' => '12948002311'],
                    ['name' => 'LandBank',             'bank_name' => 'LandBank',      'account_number' => null],
                    ['name' => 'DBP Checking',         'bank_name' => 'DBP',           'account_number' => '66419157'],
                    ['name' => 'PNB Savings',          'bank_name' => 'PNB',           'account_number' => '4003-1007-4931'],
                    ['name' => 'PNB Checking',         'bank_name' => 'PNB',           'account_number' => '4003-7000-9825'],
                    ['name' => 'PNB Diversion',        'bank_name' => 'PNB',           'account_number' => '4078-7000-4101'],
                    ['name' => 'SecBank Checking',     'bank_name' => 'Security Bank', 'account_number' => null],
                    ['name' => 'BPI Savings',          'bank_name' => 'BPI',           'account_number' => null],
                    ['name' => 'BPI Checking',         'bank_name' => 'BPI',           'account_number' => null],
                    ['name' => 'Chinabank Checking',   'bank_name' => 'Chinabank',     'account_number' => null],
                ],
            ],
            [
                'name'       => 'Corange',
                'slug'       => 'corange',
                'color'      => 'violet',
                'sort_order' => 2,
                'accounts'   => [
                    ['name' => 'Sterling',   'bank_name' => 'Sterling Bank',  'account_number' => null],
                    ['name' => 'BDO',        'bank_name' => 'BDO',            'account_number' => null],
                    ['name' => 'PNB 1',      'bank_name' => 'PNB',            'account_number' => '4003-7000-7957'],
                    ['name' => 'PNB 2',      'bank_name' => 'PNB',            'account_number' => '4003-7000-9274'],
                    ['name' => 'Secbank',    'bank_name' => 'Security Bank',  'account_number' => null],
                    ['name' => 'Chinabank',  'bank_name' => 'Chinabank',      'account_number' => null],
                ],
            ],
            [
                'name'       => 'Personal MRJ',
                'slug'       => 'personal-mrj',
                'color'      => 'emerald',
                'sort_order' => 3,
                'accounts'   => [
                    ['name' => 'Sterling',  'bank_name' => 'Sterling Bank',  'account_number' => null],
                    ['name' => 'BDO',       'bank_name' => 'BDO',            'account_number' => '12940037805'],
                    ['name' => 'Secbank',   'bank_name' => 'Security Bank',  'account_number' => null],
                ],
            ],
            [
                'name'       => 'Joint',
                'slug'       => 'joint',
                'color'      => 'amber',
                'sort_order' => 4,
                'accounts'   => [
                    ['name' => 'Sterling Joint', 'bank_name' => 'Sterling Bank', 'account_number' => null],
                    ['name' => 'PNB Joint',      'bank_name' => 'PNB',           'account_number' => null],
                ],
            ],
            [
                'name'       => 'Dollar',
                'slug'       => 'dollar',
                'color'      => 'teal',
                'sort_order' => 5,
                'accounts'   => [],
            ],
            [
                'name'       => 'Kids',
                'slug'       => 'kids',
                'color'      => 'rose',
                'sort_order' => 6,
                'accounts'   => [],
            ],
        ];

        foreach ($data as $entityData) {
            $accounts = $entityData['accounts'];
            unset($entityData['accounts']);

            $entity = Entity::updateOrCreate(
                ['slug' => $entityData['slug']],
                $entityData
            );

            foreach ($accounts as $account) {
                BankAccount::updateOrCreate(
                    ['entity_id' => $entity->id, 'name' => $account['name']],
                    array_merge($account, ['entity_id' => $entity->id, 'opening_balance' => 0])
                );
            }
        }
    }
}
