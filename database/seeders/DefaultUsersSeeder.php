<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Ministry;
use App\Models\Role;
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
        $zone       = Zone::first();
        $church     = Church::first();

        $ministryRole = Role::where('name', Role::MINISTRY_ADMIN)->firstOrFail();
        $zoneRole     = Role::where('name', Role::ZONE_ADMIN)->firstOrFail();
        $churchRole   = Role::where('name', Role::CHURCH_ADMIN)->firstOrFail();

        // ---------------------------------------------------------------
        // Ministry Administrator — full platform access
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'admin@ministry.ke'], [
            'name'        => 'Ministry Administrator',
            'password'    => Hash::make('Admin@Kenya2026'),
            'role_id'     => $ministryRole->id,
            'ministry_id' => $ministryId,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------
        // Zone Administrator — scoped to first zone
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'zone@ministry.ke'], [
            'name'        => 'Zone Administrator',
            'password'    => Hash::make('Zone@Kenya2026'),
            'role_id'     => $zoneRole->id,
            'ministry_id' => $ministryId,
            'zone_id'     => $zone?->id,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------
        // Church Administrator — scoped to first church
        // ---------------------------------------------------------------
        User::firstOrCreate(['email' => 'church@ministry.ke'], [
            'name'        => 'Church Administrator',
            'password'    => Hash::make('Church@Kenya2026'),
            'role_id'     => $churchRole->id,
            'ministry_id' => $ministryId,
            'zone_id'     => $zone?->id,
            'church_id'   => $church?->id,
            'is_active'   => true,
        ]);

        $this->command->info('✓ Default users seeded:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Ministry Admin', 'admin@ministry.ke',  'Admin@Kenya2026'],
                ['Zone Admin',     'zone@ministry.ke',   'Zone@Kenya2026'],
                ['Church Admin',   'church@ministry.ke', 'Church@Kenya2026'],
            ]
        );
    }
}
