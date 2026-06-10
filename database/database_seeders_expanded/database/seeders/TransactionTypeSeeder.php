<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Offerings
            ['code' => 'SUNDAY-OFF',   'name' => 'Sunday Offering',          'category' => 'offering',     'display_order' => 1],
            ['code' => 'EVE-OFF',      'name' => 'Evening Offering',         'category' => 'offering',     'display_order' => 2],
            ['code' => 'MID-OFF',      'name' => 'Midweek Offering',         'category' => 'offering',     'display_order' => 3],
            ['code' => 'HARV-OFF',     'name' => 'Harvesting Offering',      'category' => 'harvesting',   'display_order' => 4],
            ['code' => 'THANK-OFF',    'name' => 'Thanksgiving Offering',    'category' => 'thanksgiving', 'display_order' => 5],
            ['code' => 'SPEC-OFF',     'name' => 'Special Offering',         'category' => 'offering',     'display_order' => 6],

            // Tithes
            ['code' => 'TITHE',        'name' => 'Tithe',                    'category' => 'tithe',        'display_order' => 10],

            // Donations
            ['code' => 'CASH-DON',     'name' => 'Cash Donation',            'category' => 'donation',     'display_order' => 20],
            ['code' => 'MPESA-DON',    'name' => 'M-Pesa Donation',          'category' => 'donation',     'display_order' => 21],
            ['code' => 'CHEQUE-DON',   'name' => 'Cheque Donation',          'category' => 'donation',     'display_order' => 22],

            // Projects
            ['code' => 'BLDG-FUND',    'name' => 'Building Fund',            'category' => 'building',     'display_order' => 30],
            ['code' => 'PROJ-CONTRIB', 'name' => 'Project Contribution',     'category' => 'project',      'display_order' => 31],

            // Welfare
            ['code' => 'WELFARE',      'name' => 'Welfare / Benevolence',    'category' => 'welfare',      'display_order' => 40],

            // Pledge
            ['code' => 'PLEDGE',       'name' => 'Pledge Fulfilment',        'category' => 'pledge',       'display_order' => 50],

            // Other
            ['code' => 'OTHER',        'name' => 'Other / Miscellaneous',    'category' => 'other',        'display_order' => 99],
        ];

        foreach ($types as $typeData) {
            TransactionType::firstOrCreate(
                ['code' => $typeData['code']],
                array_merge($typeData, ['is_active' => true])
            );
        }

        $this->command->info('✓ ' . count($types) . ' transaction types seeded.');
    }
}
