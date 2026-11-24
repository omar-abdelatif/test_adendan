<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Donators extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'donators';

    protected $fillable = [
        'name',
        'mobile_number',
        'donator_type',
        'duration',
    ];

    public function outerdonations()
    {
        return $this->hasMany(OuterDonations::class);
    }

    /**
     * Donations to the sponsorship and new-tombs funds. The same donors table
     * serves every fund, so one person keeps one record whatever they give to.
     *
     * @return HasMany<FundDonation, $this>
     */
    public function fundDonations(): HasMany
    {
        return $this->hasMany(FundDonation::class, 'donator_id');
    }

    protected function getDynamicLogName(): string
    {
        return 'Donators';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->useLogName($this->getDynamicLogName())->logOnlyDirty()->setDescriptionForEvent(function (string $eventName) {
            return $this->getTranslatedDescription($eventName);
        });
    }

    protected function getTranslatedDescription(string $eventName): string
    {
        $descriptions = [
            'created' => 'تم إضافة المتبرع جديد ":name".',
            'updated' => 'تم تحديث بيانات المتبرع ":name".',
            'deleted' => 'تم حذف بيانات المتبرع ":name".',
        ];
        $arDescription = $descriptions[$eventName] ?? 'حدث غير معروف للعضو';

        return strtr($arDescription, [':name' => $this->name ?? 'اسم غير معروف']);
    }
}
