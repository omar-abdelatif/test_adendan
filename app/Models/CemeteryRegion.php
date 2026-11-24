<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An area with association tombs (القطامية، أكتوبر…). The village (is_village)
 * keeps a burial log with no tombs or chambers.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_village
 */
class CemeteryRegion extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_village', 'sort'];

    protected $casts = ['is_village' => 'boolean'];

    /** @return HasMany<CemeteryTomb, $this> */
    public function tombs(): HasMany
    {
        return $this->hasMany(CemeteryTomb::class, 'region_id');
    }

    /** @return HasMany<Burial, $this> */
    public function burials(): HasMany
    {
        return $this->hasMany(Burial::class, 'region_id');
    }
}
