<?php

namespace App\Modules\Finance\Domain\Exceptions;

use DomainException;

/**
 * A finance rule was broken. The message is Arabic and safe to show to staff.
 */
class FinanceException extends DomainException
{
    public static function dueNotFound(): self
    {
        return new self('لا يوجد مستحق بهذه البيانات لهذا العضو.');
    }

    public static function duesAlreadyIssued(string $item): self
    {
        return new self("مستحقات \"{$item}\" تم إصدارها للمشتركين بالفعل.");
    }

    public static function memberNotFound(): self
    {
        return new self('لا يوجد عضو بهذا الرقم.');
    }

    public static function nothingToPay(): self
    {
        return new self('لا يوجد مبلغ مستحق للدفع.');
    }

    public static function sameAccount(): self
    {
        return new self('لا يمكن التحويل إلى نفس الحساب.');
    }
}
