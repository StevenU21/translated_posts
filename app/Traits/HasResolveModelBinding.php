<?php

namespace App\Traits;

trait HasResolveModelBinding
{
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'id', $value)->orWhere('slug', $value)->firstOrFail();
    }
}
