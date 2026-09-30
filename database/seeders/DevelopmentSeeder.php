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

/**
 * DevelopmentSeeder
 *
 * Creates one admin per region, zone, sub-zone, and church, so that
 * every level of the hierarchy has a dedicated test account in
 * development environments.
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
        $ministryId = Ministry::currentId();
        $roles      = Role::pluck('id', 'name');

        $accounts = [];

        // ---------------------------------------------------------------
        // One region admin per region
        // ---------------------------------------------------------------
        foreach (Region::where('is_active', true)->get() as $region) {
            $slug     = strtolower(preg_replace('/[^a-z0-9]/i', '', $region->code));
            $email    = "region.{$slug}@dev.ministry.ke";
            $password = 'Dev@Region2024';

            User::firstOrCreate(['email' => $email], [
                'name'        => "Dev – {$region->name} Admin",
                'password'    => Hash::make($password),
                'role_id'     => $roles[Role::REGION_ADMIN],
                'ministry_id' => $ministryId,
                'region_id'   => $region->id,
                'is_active'   => true,
            ]);

            $accounts[] = ['Region Admin', $region->name, $email, $password];
        }

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
                'role_id'     => $roles[Role::ZONE_ADMIN],
                'ministry_id' => $ministryId,
                'zone_id'     => $zone->id,
                'is_active'   => true,
            ]);

            $accounts[] = ['Zone Admin', $zone->name, $email, $password];
        }

        // ---------------------------------------------------------------
        // One sub-zone admin per sub-zone
        // ---------------------------------------------------------------
        foreach (SubZone::where('is_active', true)->get() as $subZone) {
            $slug     = strtolower(preg_replace('/[^a-z0-9]/i', '', $subZone->code));
            $email    = "subzone.{$slug}@dev.ministry.ke";
            $password = 'Dev@SubZone2024';

            User::firstOrCreate(['email' => $email], [
                'name'        => "Dev – {$subZone->name} Admin",
                'password'    => Hash::make($password),
                'role_id'     => $roles[Role::SUB_ZONE_ADMIN],
                'ministry_id' => $ministryId,
                'sub_zone_id' => $subZone->id,
                'is_active'   => true,
            ]);

            $accounts[] = ['Sub-zone Admin', $subZone->name, $email, $password];
        }

        // ---------------------------------------------------------------
        // One church admin per church
        // ---------------------------------------------------------------
        foreach (Church::where('is_active', true)->with('subZone.zone')->get() as $church) {
            $slug     = strtolower(preg_replace('/[^a-z0-9]/i', '', $church->code));
            $email    = "church.{$slug}@dev.ministry.ke";
            $password = 'Dev@Church2024';

            User::firstOrCreate(['email' => $email], [
                'name'        => "Dev – {$church->name} Admin",
                'password'    => Hash::make($password),
                'role_id'     => $roles[Role::CHURCH_ADMIN],
                'ministry_id' => $ministryId,
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
