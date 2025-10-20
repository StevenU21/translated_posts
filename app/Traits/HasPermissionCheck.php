<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\UnauthorizedException;

trait HasPermissionCheck
{
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function checkPermission(User $user, string $permission, ?Model $model = null)
    {
        if (!$user->hasPermissionTo($permission)) {
            return $this->handleUnauthorized();
        }

        if ($model && $user->id !== $model->user_id) {
            return $this->handleUnauthorized();
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return true;
    }

    protected function handleUnauthorized()
    {
        if (request()->expectsJson()) {
            throw new UnauthorizedException(403);
        }
        // Para peticiones web (Inertia)
        return redirect()->back()->withErrors(['permission' => 'No tienes permiso para realizar esta acción.']);
    }
}
