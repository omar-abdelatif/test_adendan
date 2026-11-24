<?php

namespace App\Models;

use App\Modules\Cemeteries\Domain\Enums\Gender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A burial. Three kinds share this table:
 * - in a chamber: chamber_id set, cleared_at null (takes `places` of the chamber's capacity);
 * - past: cleared_at set (the chamber was cleared, or it came from old records);
 * - village: the region is the village and there is no chamber.
 *
 * @property int $id
 * @property int $region_id
 * @property int|null $tomb_id
 * @property int|null $chamber_id
 * @property int|null $subscriber_id
 * @property string $name
 * @property Gender $gender
 * @property int $places
 * @property Carbon|null $death_date
 * @property Carbon|null $burial_date
 * @property Carbon|null $cleared_at
 */
class Burial extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'region_id', 'tomb_id', 'chamber_id', 'subscriber_id', 'name', 'gender', 'age', 'places', 'death_place',
        'death_date', 'burial_date', 'burial_cost', 'washer', 'carrier', 'notes', 'cleared_at', 'legacy_source', 'legacy_id',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'death_date' => 'date',
        'burial_date' => 'date',
        'cleared_at' => 'datetime',
        'places' => 'integer',
    ];

    /** @return BelongsTo<CemeteryRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(CemeteryRegion::class, 'region_id');
    }

    /** @return BelongsTo<CemeteryTomb, $this> */
    public function tomb(): BelongsTo
    {
        return $this->belongsTo(CemeteryTomb::class, 'tomb_id');
    }

    /** @return BelongsTo<CemeteryChamber, $this> */
    public function chamber(): BelongsTo
    {
        return $this->belongsTo(CemeteryChamber::class, 'chamber_id');
    }

    /** @return BelongsTo<Subscribers, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Subscribers::class, 'subscriber_id');
    }

    /** @return HasMany<BurialFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(BurialFile::class);
    }

    /** @param Builder<self> $query */
    public function scopeInChambers(Builder $query): void
    {
        $query->whereNotNull('chamber_id')->whereNull('cleared_at');
    }

    /** @param Builder<self> $query */
    public function scopePast(Builder $query): void
    {
        $query->whereNotNull('cleared_at');
    }

    /** @param Builder<self> $query */
    public function scopeInVillage(Builder $query): void
    {
        $query->whereNull('cleared_at')->whereNull('chamber_id')->whereHas('region', fn ($q) => $q->where('is_village', true));
    }

    /** Where the burial is, as one line: "القطامية · مقابر القطامية · عين 3". */
    public function placeLabel(): string
    {
        return collect([$this->region?->name, $this->tomb?->name, $this->chamber ? $this->chamber->label() : null])->filter()->implode(' · ');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('Cemeteries')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "تم تسجيل دفنة {$this->name}.",
                'deleted' => "تم حذف دفنة {$this->name}.",
                default => "تم تعديل دفنة {$this->name}.",
            });
    }
}
