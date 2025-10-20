<?php

namespace App\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

class PermissionManager
{
    /**
     * Listado base de permisos por recurso.
     */
    private Collection $permissions;

    /**
     * Permisos filtrados y listos para exponer.
     */
    private Collection $filteredPermissions;

    /**
     * Permisos especiales que extienden a los básicos.
     */
    private Collection $specialPermissions;

    /**
     * Acciones por defecto para cada recurso.
     */
    private const DEFAULT_ACTIONS = ['read', 'create', 'update', 'destroy'];

    /**
     * Definición (opcional) de roles a procesar.
     */
    private Collection $rolesDefinition;

    /**
     * Bandera de control para evitar recomputar permisos.
     */
    private bool $built = false;

    public function __construct(array $permissions = [], array $specialPermissions = [])
    {
        $this->permissions = collect($permissions);
        $this->specialPermissions = collect($specialPermissions);
        $this->rolesDefinition = collect();
        $this->filteredPermissions = $this->buildPermissions();
        $this->built = true;
    }

    public static function make(array $permissions = [], array $special = []): self
    {
        return new self($permissions, $special);
    }

    public function withRoles(array $rolesDefinition): self
    {
        $this->rolesDefinition = collect($rolesDefinition);
        return $this;
    }

    public function getRolesDefinition(): array
    {
        return $this->rolesDefinition->toArray();
    }

    private function buildPermissions(): Collection
    {
        return $this->permissions
            ->mapWithKeys(function ($value, $key) {
                $resource = $this->resolveResourceName($key, $value);
                $actions = $this->determineActions($key, $value);

                $basePermissions = $actions->map(fn($action) => sprintf('%s %s', $action, $resource));
                $specials = collect($this->specialPermissions->get($resource, []))->filter();

                return [
                    $resource => $basePermissions
                        ->merge($specials)
                        ->unique()
                        ->values(),
                ];
            });
    }

    public function ensureBuilt(): self
    {
        if (!$this->built) {
            $this->filteredPermissions = $this->buildPermissions();
            $this->built = true;
        }

        return $this;
    }

    public function get(): array
    {
        return $this->filteredPermissions
            ->map(fn(Collection $actions) => $actions->all())
            ->toArray();
    }

    public function remove(array $remove): self
    {
        $removals = collect($remove);
        $clone = clone $this;

        $clone->filteredPermissions = $clone->filteredPermissions
            ->map(fn(Collection $actions) => $actions->reject(fn($permission) => $removals->contains($permission))->values())
            ->filter(fn(Collection $actions) => $actions->isNotEmpty());

        return $clone;
    }

    public function only(array $only): self
    {
        $allowed = collect($only);
        $clone = clone $this;

        $clone->filteredPermissions = $clone->filteredPermissions
            ->map(fn(Collection $actions) => $actions->filter(fn($permission) => $allowed->contains($permission))->values())
            ->filter(fn(Collection $actions) => $actions->isNotEmpty());

        return $clone;
    }

    public function all(): array
    {
        if ($this->filteredPermissions->isEmpty()) {
            return [];
        }

        return $this->filteredPermissions
            ->flatMap(fn(Collection $actions) => $actions)
            ->unique()
            ->values()
            ->all();
    }

    public function pick(array $permissionNames): array
    {
        $catalog = collect($this->all())->flip();

        return collect($permissionNames)
            ->filter(fn($permission) => $catalog->has($permission))
            ->unique()
            ->values()
            ->all();
    }

    public function roles(array $definitions): array
    {
        $allPermissions = collect($this->all());
        $catalog = $allPermissions->flip();

        return collect($definitions)
            ->map(fn($definition) => $this->compileRolePermissions($definition, $catalog, $allPermissions)->all())
            ->toArray();
    }

    public function sync(?array $rolesDefinition = null): array
    {
        if (!class_exists(PermissionModel::class) || !class_exists(RoleModel::class)) {
            throw new \RuntimeException('Spatie Permission classes not found.');
        }

        if ($rolesDefinition !== null) {
            $this->withRoles($rolesDefinition);
        }

        $this->ensureBuilt();

        $all = $this->get();
        $flat = $this->all();

        $created = 0;
        $existing = 0;

        foreach ($flat as $permName) {
            $model = PermissionModel::firstOrCreate(['name' => $permName]);
            $model->wasRecentlyCreated ? $created++ : $existing++;
        }

        $roles = $this->roles($this->rolesDefinition->toArray());
        $attached = [];

        foreach ($roles as $roleName => $permissions) {
            $role = RoleModel::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions); // sync para limpiar permisos previos obsoletos
            $attached[$roleName] = count($permissions);
        }

        return [
            'permissions_total' => count($flat),
            'permissions_created' => $created,
            'permissions_existing' => $existing,
            'roles_processed' => count($roles),
            'roles' => $attached,
        ];
    }

    protected function resolveResourceName($key, $value): string
    {
        return is_numeric($key) ? (string) $value : (string) $key;
    }

    protected function determineActions($key, $value): Collection
    {
        if (is_numeric($key)) {
            return collect(self::DEFAULT_ACTIONS);
        }

        $actions = collect(Arr::wrap($value))->filter(fn($action) => $action !== null && $action !== '');

        return $actions->whenEmpty(fn() => collect(self::DEFAULT_ACTIONS));
    }

    protected function compileRolePermissions($definition, Collection $catalog, Collection $allPermissions): Collection
    {
        if ($this->isWildcardDefinition($definition)) {
            return $allPermissions;
        }

        $normalized = $this->normalizeRoleDefinition($definition);
        $permissions = collect();

        foreach ($normalized as $resource => $items) {
            if (is_int($resource)) {
                $maybePermission = $items;
                if ($catalog->has($maybePermission)) {
                    $permissions->push($maybePermission);
                }
                continue;
            }

            $this->normalizeDefinitionItems($items)
                ->map(fn($item) => $this->expandPermissionName($item, (string) $resource))
                ->filter(fn($permission) => $catalog->has($permission))
                ->each(fn($permission) => $permissions->push($permission));
        }

        return $permissions->unique()->values();
    }

    protected function isWildcardDefinition($definition): bool
    {
        if ($definition === '*') {
            return true;
        }

        if (is_array($definition)) {
            return count($definition) === 1 && reset($definition) === '*';
        }

        return false;
    }

    protected function normalizeRoleDefinition($definition): Collection
    {
        if (!is_array($definition)) {
            return collect(['*' => $definition]);
        }

        return collect($definition);
    }

    protected function normalizeDefinitionItems($items): Collection
    {
        if (is_array($items)) {
            return collect($items)->flatten()->filter(fn($item) => $item !== null && $item !== '');
        }

        $parsed = preg_split('/[\s,|]+/', trim((string) $items)) ?: [];

        return collect($parsed)->filter(fn($item) => $item !== null && $item !== '');
    }

    protected function expandPermissionName(string $item, string $resource): string
    {
        return Str::contains($item, ' ') ? $item : sprintf('%s %s', $item, $resource);
    }
}
