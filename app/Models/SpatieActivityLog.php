<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class SpatieActivityLog extends SpatieActivity
{
    protected function customCauser(): Attribute
    {
        return Attribute::make(get: fn () => $this->causer?->name);
    }
}
