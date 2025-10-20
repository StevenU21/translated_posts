<?php

namespace App\Traits;

trait HasRouteKeyName
{
    public function getRouteKeyName()
    {
        return 'slug';
    }
}
