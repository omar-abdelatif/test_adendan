<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Donations extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'donations';

    protected $fillable = [
        'member_id',
        'invoice_no',
        'donation_type',
        'other_donation',
        'amount',
        'donation_destination',
        'donation_category',
        'payment_date',
        'subscribers_id',
    ];

    public function subscribers()
    {
        return $this->belongsTo(Subscribers::class);
    }

    protected function getDynamicLogName(): string
    {
        return 'Donations';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->useLogName($this->getDynamicLogName())->logOnlyDirty()->setDescriptionForEvent(function (string $eventName) {
            return $this->getTranslatedDescription($eventName);
        });
    }

    protected function getTranslatedDescription(string $eventName): string
    {
        // (Branches on a `payment_type` column were removed: donations have no such column.)
        $payment = [
            'created' => 'تم دفع تبرع جديد من العضو ":subscriber_name".',
            'updated' => 'تم تعديل تبرع للعضو ":subscriber_name".',
            'deleted' => 'تم حذف تبرع للعضو ":subscriber_name".',
        ][$eventName] ?? 'حدث غير معروف.';
        $subscriberName = $this->subscribers->name ?? 'غير معروف';

        return strtr($payment, [
            ':subscriber_name' => $subscriberName,
        ]);
    }
}
