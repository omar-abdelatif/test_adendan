<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The tombs as the public website and the mobile app have always read them
 * (title, tomb_type, tomb_specialization, region, tomb_guard_*), served from
 * cemetery_tombs. Read only: tombs are added and changed through the Cemeteries module.
 */
class Tombs extends Model
{
    protected $table = 'cemetery_tombs';

    protected $visible = ['id', 'title', 'tomb_type', 'tomb_specialization', 'region', 'location', 'tomb_guard_name', 'tomb_guard_number', 'created_at', 'updated_at'];

    protected $appends = ['title', 'tomb_type', 'tomb_specialization', 'region', 'tomb_guard_name', 'tomb_guard_number'];

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Tombs is read only; use the Cemeteries module.'));
        static::deleting(fn () => throw new LogicException('Tombs is read only; use the Cemeteries module.'));
    }

    /** @return BelongsTo<CemeteryRegion, $this> */
    public function regionRecord(): BelongsTo
    {
        return $this->belongsTo(CemeteryRegion::class, 'region_id');
    }

    protected function title(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['name'] ?? null);
    }

    /** The old column held who the tomb is for. */
    protected function tombType(): Attribute
    {
        return Attribute::get(fn () => ['men' => 'رجال', 'women' => 'سيدات', 'mixed' => 'مختلط'][$this->attributes['serves'] ?? ''] ?? null);
    }

    /** The old column held how the tomb is built. */
    protected function tombSpecialization(): Attribute
    {
        return Attribute::get(fn () => ['lahd' => 'لحد', 'eyes' => 'عيون'][$this->attributes['kind'] ?? ''] ?? null);
    }

    protected function region(): Attribute
    {
        return Attribute::get(fn () => $this->regionRecord?->name);
    }

    protected function tombGuardName(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['guard_name'] ?? null);
    }

    /** A number without the leading zero, as the old bigint column returned it. */
    protected function tombGuardNumber(): Attribute
    {
        return Attribute::get(function () {
            $phone = preg_replace('/\D/', '', (string) ($this->attributes['guard_phone'] ?? ''));

            return $phone === '' ? null : (int) $phone;
        });
    }
}
