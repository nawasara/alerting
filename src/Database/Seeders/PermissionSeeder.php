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

        // sysadmin is a project-wide role we assume exists; create it here
        // if missing so a fresh install bootstraps cleanly. Sysadmin handles
        // incidents but doesn't define rules — rule.manage is a code-level
        // concern owned by developers.
        $sysadmin = Role::firstOrCreate(['name' => 'sysadmin', 'guard_name' => 'web']);
        $sysadmin->givePermissionTo([
            'alerting.view',
            'alerting.acknowledge',
            'alerting.resolve',
            'alerting.silence',
        ]);
    }
}
