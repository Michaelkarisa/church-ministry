<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Ministry;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class MinistrySeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------------------
        // Ministry
        // ---------------------------------------------------------------
        $ministry = Ministry::firstOrCreate(['code' => 'KMF'], [
            'name'          => 'Kenya Ministry Fellowship',
            'address'       => 'P.O. Box 12345',
            'city'          => 'Nairobi',
            'county'        => 'Nairobi',
            'country'       => 'Kenya',
            'phone'         => '+254 700 000 000',
            'email'         => 'info@ministry.ke',
            'currency_code' => 'KES',
            'founded_year'  => 1990,
            'description'   => 'Kenya Ministry Fellowship — connecting churches across Kenya.',
            'is_active'     => true,
        ]);

        // ---------------------------------------------------------------
        // Zones
        // ---------------------------------------------------------------
        $zones = [
            ['code' => 'ZNB', 'name' => 'Nairobi Zone',      'region' => 'Nairobi'],
            ['code' => 'ZRV', 'name' => 'Rift Valley Zone',  'region' => 'Rift Valley'],
            ['code' => 'ZNZ', 'name' => 'Nyanza Zone',       'region' => 'Nyanza'],
        ];

        $createdZones = [];
        foreach ($zones as $zoneData) {
            $createdZones[$zoneData['code']] = Zone::firstOrCreate(
                ['code' => $zoneData['code']],
                array_merge($zoneData, [
                    'ministry_id' => $ministry->id,
                    'is_active'   => true,
                ])
            );
        }

        // ---------------------------------------------------------------
        // Churches
        // ---------------------------------------------------------------
        $churches = [
            ['zone_code' => 'ZNB', 'code' => 'CHR-NBI-01', 'name' => 'Nairobi Central Church',  'location' => 'CBD, Nairobi',       'pastor_name' => 'Rev. John Kamau'],
            ['zone_code' => 'ZNB', 'code' => 'CHR-NBI-02', 'name' => 'Westlands Fellowship',    'location' => 'Westlands, Nairobi', 'pastor_name' => 'Rev. Grace Wanjiku'],
            ['zone_code' => 'ZNB', 'code' => 'CHR-NBI-03', 'name' => 'Kasarani Life Church',    'location' => 'Kasarani, Nairobi',  'pastor_name' => 'Rev. Peter Mwangi'],
            ['zone_code' => 'ZRV', 'code' => 'CHR-NKR-01', 'name' => 'Nakuru Worship Centre',   'location' => 'Nakuru Town',        'pastor_name' => 'Rev. Samuel Kipchoge'],
            ['zone_code' => 'ZRV', 'code' => 'CHR-ELD-01', 'name' => 'Eldoret Grace Church',    'location' => 'Eldoret',            'pastor_name' => 'Rev. Mary Chebet'],
            ['zone_code' => 'ZNZ', 'code' => 'CHR-KSM-01', 'name' => 'Kisumu Lakeside Church',  'location' => 'Kisumu',             'pastor_name' => 'Rev. David Ouma'],
        ];

        foreach ($churches as $churchData) {
            $zone = $createdZones[$churchData['zone_code']];
            Church::firstOrCreate(
                ['code' => $churchData['code']],
                [
                    'zone_id'     => $zone->id,
                    'name'        => $churchData['name'],
                    'location'    => $churchData['location'],
                    'pastor_name' => $churchData['pastor_name'],
                    'is_active'   => true,
                ]
            );
        }

        $this->command->info("✓ Ministry, {$ministry->zones()->count()} zones, and churches seeded.");
    }
}
