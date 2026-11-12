<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = [
            'fiscal-years' => ['view', 'create', 'update', 'delete'],
            'targets' => ['view', 'create', 'update', 'delete'],
            'outcome-areas' => ['view', 'create', 'update', 'delete'],
            'programs' => ['view', 'create', 'update', 'delete'],
            'offices' => ['view', 'create', 'update', 'delete'],
            'performance-indicators' => ['view', 'create', 'update', 'delete'],
            'accomplishments' => ['view', 'create', 'update', 'delete'],
            'allotments' => ['view', 'create', 'update'],
            'allotment-entries' => ['view', 'create', 'update'],
            'uacs' => ['view', 'create', 'update'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissionName = "{$module}.{$action}";

                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            }
        }

        $roles = [
            'RICTU',
            'SRMU',
            'BUDGET',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        // RICTU: All application permissions
        Role::findByName('RICTU', 'web')
            ->syncPermissions(Permission::where('guard_name', 'web')->get());

        // SRMU: Performance management
        Role::findByName('SRMU', 'web')
            ->syncPermissions([
                'fiscal-years.view',
                'fiscal-years.create',
                'fiscal-years.update',
                'fiscal-years.delete',

                'targets.view',
                'targets.create',
                'targets.update',
                'targets.delete',

                'outcome-areas.view',
                'outcome-areas.create',
                'outcome-areas.update',
                'outcome-areas.delete',

                'programs.view',
                'programs.create',
                'programs.update',
                'programs.delete',

                'offices.view',

                'performance-indicators.view',
                'performance-indicators.create',
                'performance-indicators.update',
                'performance-indicators.delete',

                'accomplishments.view',
                'accomplishments.create',
                'accomplishments.update',
                'accomplishments.delete',

                'allotments.view',
                'allotment-entries.view',
                'uacs.view',
            ]);

        // BUDGET: Financial management
        Role::findByName('BUDGET', 'web')
            ->syncPermissions([
                'fiscal-years.view',
                'targets.view',
                'outcome-areas.view',
                'programs.view',
                'offices.view',
                'performance-indicators.view',

                'accomplishments.view',

                'allotments.view',
                'allotments.create',
                'allotments.update',

                'allotment-entries.view',
                'allotment-entries.create',
                'allotment-entries.update',

                'uacs.view',
                'uacs.create',
                'uacs.update'
            ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
