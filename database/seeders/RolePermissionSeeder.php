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
            'description'  => 'Full access to all regions, zones, sub-zones, churches, and financial records.',
            'level'        => 1,
        ]);

        $regionAdmin = Role::firstOrCreate(['name' => Role::REGION_ADMIN], [
            'display_name' => 'Region Administrator',
            'description'  => 'Access to all zones, sub-zones and churches within their assigned region.',
            'level'        => 2,
        ]);

        $zoneAdmin = Role::firstOrCreate(['name' => Role::ZONE_ADMIN], [
            'display_name' => 'Zone Administrator',
            'description'  => 'Access to all sub-zones and churches within their assigned zone.',
            'level'        => 3,
        ]);

        $subZoneAdmin = Role::firstOrCreate(['name' => Role::SUB_ZONE_ADMIN], [
            'display_name' => 'Sub-zone Administrator',
            'description'  => 'Access to all churches within their assigned sub-zone.',
            'level'        => 4,
        ]);

        $churchAdmin = Role::firstOrCreate(['name' => Role::CHURCH_ADMIN], [
            'display_name' => 'Church Administrator',
            'description'  => 'Access to their assigned church only.',
            'level'        => 5,
        ]);

        // ---------------------------------------------------------------
        // Permissions
        // ---------------------------------------------------------------
        $permissions = [
            // Ministry
            ['name' => 'ministry.view',   'display_name' => 'View Ministry',   'group' => 'ministry'],
            ['name' => 'ministry.edit',   'display_name' => 'Edit Ministry',   'group' => 'ministry'],

            // Regions
            ['name' => 'regions.view',    'display_name' => 'View Regions',    'group' => 'regions'],
            ['name' => 'regions.manage',  'display_name' => 'Manage Regions',  'group' => 'regions'],

            // Zones
            ['name' => 'zones.view',      'display_name' => 'View Zones',      'group' => 'zones'],
            ['name' => 'zones.create',    'display_name' => 'Create Zones',    'group' => 'zones'],
            ['name' => 'zones.edit',      'display_name' => 'Edit Zones',      'group' => 'zones'],
            ['name' => 'zones.delete',    'display_name' => 'Delete Zones',    'group' => 'zones'],

            // Sub-zones
            ['name' => 'sub_zones.view',   'display_name' => 'View Sub-zones',   'group' => 'sub_zones'],
            ['name' => 'sub_zones.manage', 'display_name' => 'Manage Sub-zones', 'group' => 'sub_zones'],

            // Churches
            ['name' => 'churches.view',   'display_name' => 'View Churches',   'group' => 'churches'],
            ['name' => 'churches.create', 'display_name' => 'Create Churches', 'group' => 'churches'],
            ['name' => 'churches.edit',   'display_name' => 'Edit Churches',   'group' => 'churches'],
            ['name' => 'churches.delete', 'display_name' => 'Delete Churches', 'group' => 'churches'],

            // Leadership
            ['name' => 'leadership.view',   'display_name' => 'View Leadership',   'group' => 'leadership'],
            ['name' => 'leadership.manage', 'display_name' => 'Manage Leadership', 'group' => 'leadership'],

            // Events
            ['name' => 'events.view',   'display_name' => 'View Events',   'group' => 'events'],
            ['name' => 'events.manage', 'display_name' => 'Manage Events', 'group' => 'events'],

            // Projects
            ['name' => 'projects.view',   'display_name' => 'View Projects',   'group' => 'projects'],
            ['name' => 'projects.manage', 'display_name' => 'Manage Projects', 'group' => 'projects'],

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

            // Analytics — overview (non-financial, all roles) vs. financial drill-downs
            ['name' => 'analytics.overview',  'display_name' => 'Structure Overview', 'group' => 'analytics'],
            ['name' => 'analytics.church',    'display_name' => 'Church Analytics',   'group' => 'analytics'],
            ['name' => 'analytics.zone',      'display_name' => 'Zone Analytics',     'group' => 'analytics'],
            ['name' => 'analytics.ministry',  'display_name' => 'Ministry Analytics', 'group' => 'analytics'],

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
        // Region Admin — everything within their region (no ministry
        // edit, no region/user management, no ministry-wide analytics)
        // ---------------------------------------------------------------
        $regionPermNames = [
            'regions.view',
            'zones.view', 'zones.create', 'zones.edit',
            'sub_zones.view', 'sub_zones.manage',
            'churches.view', 'churches.create', 'churches.edit',
            'leadership.view', 'leadership.manage',
            'events.view', 'events.manage',
            'projects.view', 'projects.manage',
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.verify',
            'analytics.overview', 'analytics.church', 'analytics.zone',
            'logs.view',
        ];
        $regionAdmin->permissions()->sync(
            collect($permissionObjects)->only($regionPermNames)->pluck('id')->toArray()
        );

        // ---------------------------------------------------------------
        // Zone Admin — zone/church/member/transaction perms (no users, no ministry edit, no ministry analytics)
        // ---------------------------------------------------------------
        $zonePermNames = [
            'regions.view',
            'zones.view',
            'sub_zones.view', 'sub_zones.manage',
            'churches.view', 'churches.create', 'churches.edit',
            'leadership.view', 'leadership.manage',
            'events.view', 'events.manage',
            'projects.view', 'projects.manage',
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.verify',
            'analytics.overview', 'analytics.church', 'analytics.zone',
            'logs.view',
        ];
        $zoneAdmin->permissions()->sync(
            collect($permissionObjects)->only($zonePermNames)->pluck('id')->toArray()
        );

        // ---------------------------------------------------------------
        // Sub-zone Admin — churches within their sub-zone only (no
        // deleting churches, no zone/region management)
        // ---------------------------------------------------------------
        $subZonePermNames = [
            'regions.view',
            'sub_zones.view',
            'churches.view', 'churches.create', 'churches.edit',
            'leadership.view', 'leadership.manage',
            'events.view', 'events.manage',
            'projects.view', 'projects.manage',
            'members.view', 'members.create', 'members.edit',
            'transactions.view', 'transactions.create', 'transactions.edit',
            'analytics.overview', 'analytics.church',
            'logs.view',
        ];
        $subZoneAdmin->permissions()->sync(
            collect($permissionObjects)->only($subZonePermNames)->pluck('id')->toArray()
        );

        // ---------------------------------------------------------------
        // Church Admin — church-scoped permissions only
        // ---------------------------------------------------------------
        $churchPermNames = [
            'regions.view',
            'churches.view', 'churches.edit',
            'leadership.view', 'leadership.manage',
            'events.view', 'events.manage',
            'projects.view', 'projects.manage',
            'members.view', 'members.create', 'members.edit',
            'transactions.view', 'transactions.create', 'transactions.edit',
            'analytics.overview', 'analytics.church',
            'logs.view',
        ];
        $churchAdmin->permissions()->sync(
            collect($permissionObjects)->only($churchPermNames)->pluck('id')->toArray()
        );

        $this->command->info('✓ Roles and permissions seeded.');
    }
}
