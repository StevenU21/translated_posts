<?php

namespace Database\Seeders;

use App\Classes\PermissionManager;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    const PERMISSIONS = [
        'posts' => [],
        'post_translations' => []
    ];

    const SPECIAL_PERMISSIONS = [];

    const ROLES = [
        'admin' => '*',
    ];

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $manager = new PermissionManager(self::PERMISSIONS, self::SPECIAL_PERMISSIONS);
        $manager->withRoles(self::ROLES)->sync();
    }
}
