<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,   // roles, permissions, role_permissions
            MinistrySeeder::class,         // ministry, zones, churches
            DefaultUsersSeeder::class,     // ministry / zone / church admin accounts
            TransactionTypeSeeder::class,  // offering, tithe, donation … types
            MemberSeeder::class,           // 15-25 members per church
            TransactionSeeder::class,      // 52wk offerings, 12mo tithes, specials
        ]);
    }
}
