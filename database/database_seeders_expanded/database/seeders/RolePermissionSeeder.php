<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------------------
        // Roles
        // ---------------------------------------------------------------
        $ministryAdmin = Role::firstOrCreate(['name' => Role::MINISTRY_ADMIN], [
            'display_name' => 'Ministry Administrator',
            'description'  => 'Full access to all zones, churches, and financial records.',
            'level'        => 1,
        ]);

        $zoneAdmin = Role::firstOrCreate(['name' => Role::ZONE_ADMIN], [
            'display_name' => 'Zone Administrator',
            'description'  => 'Access to all churches within their assigned zone.',
            'level'        => 2,
        ]);

        $churchAdmin = Role::firstOrCreate(['name' => Role::CHURCH_ADMIN], [
            'display_name' => 'Church Administrator',
            'description'  => 'Access to their assigned church only.',
            'level'        => 3,
        ]);

        // ---------------------------------------------------------------
        // Permissions
        // ---------------------------------------------------------------
        $permissions = [
            // Ministry
            ['name' => 'ministry.view',   'display_name' => 'View Ministry',   'group' => 'ministry'],
            ['name' => 'ministry.edit',   'display_name' => 'Edit Ministry',   'group' => 'ministry'],

            // Zones
            ['name' => 'zones.view',      'display_name' => 'View Zones',      'group' => 'zones'],
            ['name' => 'zones.create',    'display_name' => 'Create Zones',    'group' => 'zones'],
            ['name' => 'zones.edit',      'display_name' => 'Edit Zones',      'group' => 'zones'],
            ['name' => 'zones.delete',    'display_name' => 'Delete Zones',    'group' => 'zones'],

            // Churches
            ['name' => 'churches.view',   'display_name' => 'View Churches',   'group' => 'churches'],
            ['name' => 'churches.create', 'display_name' => 'Create Churches', 'group' => 'churches'],
            ['name' => 'churches.edit',   'display_name' => 'Edit Churches',   'group' => 'churches'],
            ['name' => 'churches.delete', 'display_name' => 'Delete Churches', 'group' => 'churches'],

            // Members
            ['name' => 'members.view',    'display_name' => 'View Members',    'group' => 'members'],
            ['name' => 'members.create',  'display_name' => 'Add Members',     'group' => 'members'],
            ['name' => 'members.edit',    'display_name' => 'Edit Members',    'group' => 'members'],
            ['name' => 'members.delete',  'display_name' => 'Delete Members',  'group' => 'members'],

            // Transactions
            ['name' => 'transactions.view',   'display_name' => 'View Transactions',   'group' => 'transactions'],
            ['name' => 'transactions.create', 'display_name' => 'Record Transactions', 'group' => 'transactions'],
            ['name' => 'transactions.edit',   'display_name' => 'Edit Transactions',   'group' => 'transactions'],
            ['name' => 'transactions.delete', 'display_name' => 'Delete Transactions', 'group' => 'transactions'],
            ['name' => 'transactions.verify', 'display_name' => 'Verify Transactions', 'group' => 'transactions'],

            // Transaction Types
            ['name' => 'transaction_types.manage', 'display_name' => 'Manage Transaction Types', 'group' => 'transactions'],

            // Users
            ['name' => 'users.view',   'display_name' => 'View Users',   'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'users.edit',   'display_name' => 'Edit Users',   'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'users'],

            // Analytics
            ['name' => 'analytics.church',   'display_name' => 'Church Analytics',   'group' => 'analytics'],
            ['name' => 'analytics.zone',     'display_name' => 'Zone Analytics',     'group' => 'analytics'],
            ['name' => 'analytics.ministry', 'display_name' => 'Ministry Analytics', 'group' => 'analytics'],

            // Logs
            ['name' => 'logs.view', 'display_name' => 'View Activity Logs', 'group' => 'logs'],
        ];

        $permissionObjects = [];
        foreach ($permissions as $perm) {
            $permissionObjects[$perm['name']] = Permission::firstOrCreate(
                ['name' => $perm['name']],
                array_merge($perm, ['description' => $perm['display_name']])
            );
        }

        // ---------------------------------------------------------------
        // Ministry Admin — all permissions
        // ---------------------------------------------------------------
        $ministryAdmin->permissions()->sync(
            collect($permissionObjects)->pluck('id')->toArray()
        );

        // ---------------------------------------------------------------
        // Zone Admin — zone/church/member/transaction perms (no users, no ministry edit, no ministry analytics)
        // ---------------------------------------------------------------
        $zonePermNames = [
            'zones.view',
            'churches.view', 'churches.create', 'churches.edit',
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.verify',
            'analytics.church', 'analytics.zone',
            'logs.view',
        ];
        $zoneAdmin->permissions()->sync(
            collect($permissionObjects)->only($zonePermNames)->pluck('id')->toArray()
        );

        // ---------------------------------------------------------------
        // Church Admin — church-scoped permissions only
        // ---------------------------------------------------------------
        $churchPermNames = [
            'churches.view', 'churches.edit',
            'members.view', 'members.create', 'members.edit',
            'transactions.view', 'transactions.create', 'transactions.edit',
            'analytics.church',
            'logs.view',
        ];
        $churchAdmin->permissions()->sync(
            collect($permissionObjects)->only($churchPermNames)->pluck('id')->toArray()
        );

        $this->command->info('✓ Roles and permissions seeded.');
    }
}
