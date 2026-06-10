<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Ministry;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DevelopmentSeeder
 *
 * Creates one zone admin per zone and one church admin per church so that
 * every scope has a dedicated test account in development environments.
 *
 * Run via:
 *   php artisan db:seed --class=DevelopmentSeeder
 *
 * Do NOT include this in the production DatabaseSeeder.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $ministryId  = Ministry::currentId();
        $zoneRole    = Role::where('name', Role::ZONE_ADMIN)->firstOrFail();
        $churchRole  = Role::where('name', Role::CHURCH_ADMIN)->firstOrFail();

        $accounts = [];

        // ---------------------------------------------------------------
        // One zone admin per zone
        // ---------------------------------------------------------------
        foreach (Zone::where('is_active', true)->get() as $zone) {
            $slug     = strtolower(preg_replace('/[^a-z0-9]/i', '', $zone->code));
            $email    = "zone.{$slug}@dev.ministry.ke";
            $password = 'Dev@Zone2024';

            User::firstOrCreate(['email' => $email], [
                'name'        => "Dev – {$zone->name} Admin",
                'password'    => Hash::make($password),
                'role_id'     => $zoneRole->id,
                'ministry_id' => $ministryId,
                'zone_id'     => $zone->id,
                'is_active'   => true,
            ]);

            $accounts[] = ['Zone Admin', $zone->name, $email, $password];
        }

        // ---------------------------------------------------------------
        // One church admin per church
        // ---------------------------------------------------------------
        foreach (Church::where('is_active', true)->with('zone')->get() as $church) {
            $slug     = strtolower(preg_replace('/[^a-z0-9]/i', '', $church->code));
            $email    = "church.{$slug}@dev.ministry.ke";
            $password = 'Dev@Church2024';

            User::firstOrCreate(['email' => $email], [
                'name'        => "Dev – {$church->name} Admin",
                'password'    => Hash::make($password),
                'role_id'     => $churchRole->id,
                'ministry_id' => $ministryId,
                'zone_id'     => $church->zone_id,
                'church_id'   => $church->id,
                'is_active'   => true,
            ]);

            $accounts[] = ['Church Admin', $church->name, $email, $password];
        }

        $this->command->info('✓ Development accounts created:');
        $this->command->table(
            ['Role', 'Scope', 'Email', 'Password'],
            $accounts
        );
    }
}
