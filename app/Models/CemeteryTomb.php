<?php

namespace App\Models;

use App\Modules\Cemeteries\Domain\Enums\TombKind;
use App\Modules\Cemeteries\Domain\Enums\TombServes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One association tomb with its chambers. Written only through the Cemeteries actions.
 *
 * @property int $id
 * @property int $region_id
 * @property string $name
 * @property TombKind $kind
 * @property TombServes $serves
 * @property int|null $annual_cost
 * @property string|null $location
 * @property string|null $guard_name
 * @property string|null $guard_phone
 */
class CemeteryTomb extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'cemetery_tombs';

    protected $fillable = ['region_id', 'name', 'kind', 'serves', 'annual_cost', 'location', 'guard_name', 'guard_phone', 'directory_id'];

    protected $casts = ['kind' => TombKind::class, 'serves' => TombServes::class];

    /** @return BelongsTo<CemeteryRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(CemeteryRegion::class, 'region_id');
    }

    /** @return HasMany<CemeteryChamber, $this> */
    public function chambers(): HasMany
    {
        return $this->hasMany(CemeteryChamber::class, 'tomb_id')->orderBy('number');
    }

    /** @return HasMany<Burial, $this> */
    public function burials(): HasMany
    {
        return $this->hasMany(Burial::class, 'tomb_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('Cemeteries')
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => "تم إضافة {$this->name}.",
                'deleted' => "تم حذف {$this->name}.",
                default => "تم تعديل بيانات {$this->name}.",
            });
    }
}
