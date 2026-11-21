<?php

namespace App\Modules\Finance\Domain\Online;

enum OnlinePaymentResult
{
    case Applied;
    case AlreadyRecorded;
    case BadSignature;
    case Invalid;
    case NotSuccessful;

    public function httpStatus(): int
    {
        return match ($this) {
            self::Applied, self::AlreadyRecorded => 200,
            self::BadSignature => 403,
            self::Invalid, self::NotSuccessful => 400,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Applied => 'تم الدفع بنجاح',
            self::AlreadyRecorded => 'تم تسجيل العملية من قبل',
            self::BadSignature => 'بيانات غير صحيحة (فشل التشفير)',
            self::Invalid => 'بيانات غير صحيحة',
            self::NotSuccessful => 'فشل الدفع',
        };
    }
}
