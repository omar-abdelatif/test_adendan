<?php

namespace App\Models;

use App\Modules\Members\Domain\MemberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subscribers extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity;

    protected $table = 'subscribers';

    protected $hidden = ['password'];

    protected $fillable = [
        'member_id',
        'name',
        'slug',
        'nickname',
        'ssn',
        'address',
        'educational_qualification',
        'qualification_date',
        'job',
        'job_destination',
        'job_tel',
        'job_address',
        'home_tel',
        'martial_status',
        'birthdate',
        'mobile_no',
        'another_mobile_no',
        'membership_type',
        'id_img',
        'status',
        'img',
        'tomb_name',
        'fcm_token',
    ];

    /** Members who are still charged dues and counted in reports (everyone but the deceased). */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', MemberStatus::Deceased->value));
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class, 'member_id', 'member_id');
    }

    /**
     * Every fund's rows; the member's screens narrow this with inFund(Fund::General).
     *
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'member_id', 'member_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donations::class);
    }

    /** The tomb the member's family uses, matched by its name. @return BelongsTo<CemeteryTomb, $this> */
    public function tomb(): BelongsTo
    {
        return $this->belongsTo(CemeteryTomb::class, 'tomb_name', 'name');
    }

    public function smssubscribers(): HasOne
    {
        return $this->hasOne(SMSSubscribers::class, 'member_id', 'member_id');
    }

    protected function getDynamicLogName(): string
    {
        return 'Subscribers';
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
            'created' => 'تم إضافة العضو ":name".',
            'updated' => 'تم تحديث العضو ":name".',
            'deleted' => 'تم حذف العضو ":name".',
        ];
        $arDescription = $descriptions[$eventName] ?? 'حدث غير معروف للعضو';

        return strtr($arDescription, [':name' => $this->name ?? 'اسم غير معروف']);
    }

    public function getAuthIdentifierName()
    {
        return 'ssn';
    }
}
