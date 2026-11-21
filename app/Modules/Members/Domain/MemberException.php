<?php

namespace App\Modules\Members\Domain;

use DomainException;

/** A membership rule was broken. The message is Arabic and safe to show to staff. */
class MemberException extends DomainException
{
    public static function hasFinancialHistory(): self
    {
        return new self('لا يمكن حذف عضو له معاملات مالية مسجلة. يمكن تعديل حالته بدلًا من الحذف.');
    }

    public static function yearCostMissing(int $year): self
    {
        return new self("لم يتم تحديد قيمة الاشتراك السنوي لسنة {$year}. أضفها من صفحة الإشتراك السنوي أولًا.");
    }

    public static function notFound(): self
    {
        return new self('لا يوجد عضو بهذه البيانات.');
    }

    public static function nothingToUpdate(): self
    {
        return new self('لا توجد بيانات لتحديثها');
    }
}
