<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Leadership;
use App\Models\Ministry;
use App\Models\Region;
use App\Models\SubZone;
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
        // Regions (Ministry -> Region -> Zone -> Sub-zone -> Church)
        // ---------------------------------------------------------------
        $regions = [
            ['code' => 'RNB', 'name' => 'Nairobi Region'],
            ['code' => 'RRV', 'name' => 'Rift Valley Region'],
            ['code' => 'RNZ', 'name' => 'Nyanza Region'],
        ];

        $createdRegions = [];
        foreach ($regions as $regionData) {
            $createdRegions[$regionData['code']] = Region::firstOrCreate(
                ['code' => $regionData['code']],
                array_merge($regionData, ['ministry_id' => $ministry->id, 'is_active' => true])
            );
        }

        // ---------------------------------------------------------------
        // Zones — one per region for this seed data
        // ---------------------------------------------------------------
        $zones = [
            ['code' => 'ZNB', 'name' => 'Nairobi Zone',     'region_code' => 'RNB'],
            ['code' => 'ZRV', 'name' => 'Rift Valley Zone', 'region_code' => 'RRV'],
            ['code' => 'ZNZ', 'name' => 'Nyanza Zone',      'region_code' => 'RNZ'],
        ];

        $createdZones = [];
        foreach ($zones as $zoneData) {
            $createdZones[$zoneData['code']] = Zone::firstOrCreate(
                ['code' => $zoneData['code']],
                [
                    'name'      => $zoneData['name'],
                    'region_id' => $createdRegions[$zoneData['region_code']]->id,
                    'is_active' => true,
                ]
            );
        }

        // ---------------------------------------------------------------
        // Sub-zones
        // ---------------------------------------------------------------
        $subZones = [
            ['code' => 'SZ-NBI-C', 'name' => 'Nairobi Central Sub-zone', 'zone_code' => 'ZNB'],
            ['code' => 'SZ-NBI-W', 'name' => 'Nairobi West Sub-zone',    'zone_code' => 'ZNB'],
            ['code' => 'SZ-RV-N',  'name' => 'Rift Valley North Sub-zone', 'zone_code' => 'ZRV'],
            ['code' => 'SZ-NZ-K',  'name' => 'Nyanza Kisumu Sub-zone',   'zone_code' => 'ZNZ'],
        ];

        $createdSubZones = [];
        foreach ($subZones as $subZoneData) {
            $createdSubZones[$subZoneData['code']] = SubZone::firstOrCreate(
                ['code' => $subZoneData['code']],
                [
                    'name'      => $subZoneData['name'],
                    'zone_id'   => $createdZones[$subZoneData['zone_code']]->id,
                    'is_active' => true,
                ]
            );
        }

        // ---------------------------------------------------------------
        // Churches — general details + assets. Leadership is seeded
        // separately below (Members is dormant, so leaders are their
        // own standalone records, not linked to Member).
        // ---------------------------------------------------------------
        $churches = [
            ['sub_zone_code' => 'SZ-NBI-C', 'code' => 'CHR-NBI-01', 'name' => 'Nairobi Central Church', 'location' => 'CBD, Nairobi',       'land_status' => 'bought', 'building_status' => 'built',             'pastor' => 'Rev. John Kamau'],
            ['sub_zone_code' => 'SZ-NBI-W', 'code' => 'CHR-NBI-02', 'name' => 'Westlands Fellowship',   'location' => 'Westlands, Nairobi', 'land_status' => 'rented', 'building_status' => 'rented',            'pastor' => 'Rev. Grace Wanjiku'],
            ['sub_zone_code' => 'SZ-NBI-W', 'code' => 'CHR-NBI-03', 'name' => 'Kasarani Life Church',   'location' => 'Kasarani, Nairobi',  'land_status' => 'bought', 'building_status' => 'under_construction', 'pastor' => 'Rev. Peter Mwangi'],
            ['sub_zone_code' => 'SZ-RV-N',  'code' => 'CHR-NKR-01', 'name' => 'Nakuru Worship Centre',  'location' => 'Nakuru Town',        'land_status' => 'bought', 'building_status' => 'built',             'pastor' => 'Rev. Samuel Kipchoge'],
            ['sub_zone_code' => 'SZ-RV-N',  'code' => 'CHR-ELD-01', 'name' => 'Eldoret Grace Church',   'location' => 'Eldoret',            'land_status' => 'rented', 'building_status' => 'rented',            'pastor' => 'Rev. Mary Chebet'],
            ['sub_zone_code' => 'SZ-NZ-K',  'code' => 'CHR-KSM-01', 'name' => 'Kisumu Lakeside Church', 'location' => 'Kisumu',             'land_status' => 'bought', 'building_status' => 'built',             'pastor' => 'Rev. David Ouma'],
        ];

        foreach ($churches as $churchData) {
            $church = Church::firstOrCreate(
                ['code' => $churchData['code']],
                [
                    'sub_zone_id'     => $createdSubZones[$churchData['sub_zone_code']]->id,
                    'name'            => $churchData['name'],
                    'location'        => $churchData['location'],
                    'land_status'     => $churchData['land_status'],
                    'building_status' => $churchData['building_status'],
                    'is_active'       => true,
                ]
            );

            // Leadership — primary Pastor record for each church.
            Leadership::firstOrCreate(
                ['church_id' => $church->id, 'name' => $churchData['pastor']],
                ['role' => 'Pastor', 'is_primary' => true, 'is_active' => true]
            );
        }

        $this->command->info(
            "✓ Ministry seeded: {$ministry->regions()->count()} regions, "
            . Zone::count() . ' zones, ' . SubZone::count() . ' sub-zones, '
            . Church::count() . ' churches, ' . Leadership::count() . ' leaders.'
        );
    }
}
