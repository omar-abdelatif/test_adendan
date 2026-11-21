<?php

namespace App\Modules\Access\Domain;

/**
 * The permission names the code checks (route middleware and @can in views).
 * Deleting or renaming one of these from the dashboard would lock staff out
 * of that section, so they are protected.
 */
final class Permissions
{
    public const ALL = [
        'لوحة التحكم',
        'الاخبار',
        'المشتركين',
        'مشترك جديد',
        'الاشتراكات',
        'الإشتراك السنوي',
        'المديونيات',
        'مديونية الاشتراك',
        'مديونية التبرعات',
        'التبرعات',
        'المتبرعين',
        'التبرعات الخارجية',
        'النثريات',
        'مجلس الادارة',
        'اللجان',
        'الحرفيين',
        'المقابر',
        'الافراح',
        'الرسائل',
        'التقارير',
        'المستخدمين',
        'الادوار',
        'الصلاحيات',
        'سجل النشاطات',
        'sms-delete-subscriber',
    ];

    /** The role that holds everything; it cannot be deleted. */
    public const SUPER_ROLE = 'master';

    public static function isBuiltIn(string $name): bool
    {
        return in_array($name, self::ALL, true);
    }
}
