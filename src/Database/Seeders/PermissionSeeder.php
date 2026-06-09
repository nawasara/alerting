<?php

namespace Nawasara\Alerting\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'alerting.view',
            'alerting.acknowledge',
            'alerting.resolve',
            'alerting.silence',
            'alerting.rule.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        if ($developer = Role::where('name', 'developer')->first()) {
            $developer->givePermissionTo($permissions);
        }

        if ($sysadmin = Role::where('name', 'sysadmin')->first()) {
            // Sysadmin handles incidents, not rule definitions — rule.manage
            // is a code-level concern owned by developers.
            $sysadmin->givePermissionTo([
                'alerting.view',
                'alerting.acknowledge',
                'alerting.resolve',
                'alerting.silence',
            ]);
        }
    }
}
