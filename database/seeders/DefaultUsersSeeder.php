<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Ministry;
use App\Models\Region;
use App\Models\Role;
use App\Models\SubZone;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        // There is exactly one ministry — always use its ID
        $ministryId = Ministry::currentId();
        $region     = Region::first();
        $zone       = Zone::first();
        $subZone    = SubZone::first();
        $church     = Church::first();

        $roles = Role::pluck('id', 'name');

        // ---------------------------------------------------------------
        // Ministry Administrator — full platform access
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'admin@ministry.ke'], [
            'name'        => 'Ministry Administrator',
            'password'    => Hash::make('Admin@Kenya2026'),
            'role_id'     => $roles[Role::MINISTRY_ADMIN],
            'ministry_id' => $ministryId,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------
        // Region Administrator — scoped to the first region
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'region@ministry.ke'], [
            'name'        => 'Region Administrator',
            'password'    => Hash::make('Region@Kenya2026'),
            'role_id'     => $roles[Role::REGION_ADMIN],
            'ministry_id' => $ministryId,
            'region_id'   => $region?->id,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------
        // Zone Administrator — scoped to the first zone
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'zone@ministry.ke'], [
            'name'        => 'Zone Administrator',
            'password'    => Hash::make('Zone@Kenya2026'),
            'role_id'     => $roles[Role::ZONE_ADMIN],
            'ministry_id' => $ministryId,
            'zone_id'     => $zone?->id,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------
        // Sub-zone Administrator — scoped to the first sub-zone
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'subzone@ministry.ke'], [
            'name'        => 'Sub-zone Administrator',
            'password'    => Hash::make('SubZone@Kenya2026'),
            'role_id'     => $roles[Role::SUB_ZONE_ADMIN],
            'ministry_id' => $ministryId,
            'sub_zone_id' => $subZone?->id,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------
        // Church Administrator — scoped to the first church
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'church@ministry.ke'], [
            'name'        => 'Church Administrator',
            'password'    => Hash::make('Church@Kenya2026'),
            'role_id'     => $roles[Role::CHURCH_ADMIN],
            'ministry_id' => $ministryId,
            'church_id'   => $church?->id,
            'is_active'   => true,
        ]);

        $this->command->info('✓ Default users seeded:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Ministry Admin',  'admin@ministry.ke',    'Admin@Kenya2026'],
                ['Region Admin',    'region@ministry.ke',   'Region@Kenya2026'],
                ['Zone Admin',      'zone@ministry.ke',     'Zone@Kenya2026'],
                ['Sub-zone Admin',  'subzone@ministry.ke',  'SubZone@Kenya2026'],
                ['Church Admin',    'church@ministry.ke',   'Church@Kenya2026'],
            ]
        );
    }
}
