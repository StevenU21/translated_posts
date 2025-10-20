<?php

namespace App\Classes;

class PermissionHelper
{
    /**
     * Get permissions for the current user for a resource.
     * @param string $resource Resource name (e.g. 'exercises')
     * @param array $actions Optional list of actions (default: basic CRUD)
     * @return array
     */
    public static function getPermissions(string $resource, array $extra = []): array
    {
        $user = auth()->user();
        $map = [
            'create' => 'create',
            'view'   => 'read',
            'edit'   => 'update',
            'delete' => 'destroy',
        ];
        // Agregar permisos extra al map
        foreach ($extra as $perm) {
            $map[$perm] = $perm;
        }
        $permissions = [];
        foreach ($map as $key => $action) {
            $permissions[$key] = $user ? $user->can("{$action} {$resource}") : false;
        }
        return $permissions;
    }
}
