<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One عين (several burials) or لحد (one burial) inside a tomb.
 *
 * @property int $id
 * @property int $tomb_id
 * @property int $number
 * @property int $capacity
 * @property Carbon|null $last_cleared_at
 */
class CemeteryChamber extends Model
{
    use HasFactory;

    protected $table = 'cemetery_chambers';

    protected $fillable = ['tomb_id', 'number', 'capacity', 'last_cleared_at'];

    protected $casts = ['last_cleared_at' => 'datetime', 'capacity' => 'integer', 'number' => 'integer'];

    /** @return BelongsTo<CemeteryTomb, $this> */
    public function tomb(): BelongsTo
    {
        return $this->belongsTo(CemeteryTomb::class, 'tomb_id');
    }

    /** @return HasMany<Burial, $this> */
    public function burials(): HasMany
    {
        return $this->hasMany(Burial::class, 'chamber_id');
    }

    /** Burials still in the chamber (not cleared). @return HasMany<Burial, $this> */
    public function activeBurials(): HasMany
    {
        return $this->burials()->whereNull('cleared_at');
    }

    /** "عين 3" or "لحد 12". */
    public function label(): string
    {
        return $this->tomb->kind->unit().' '.$this->number;
    }
}
