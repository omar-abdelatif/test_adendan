<?php

namespace App\Modules\Finance\Domain\Enums;

/**
 * What a payment_transactions row is for (column `transaction_type`).
 *
 * Values are the exact Arabic strings already stored in production, so old and
 * new rows report together. Legacy spellings are kept as cases only so reports
 * can still find them; new rows never use them.
 */
enum TransactionType: string
{
    case Subscription = 'إشتراك';
    case NewSubscription = 'إشتراك جديد';
    case AnnualDonation = 'تبرع سنوي';
    case Donation = 'تبرعات';
    case OuterDonation = 'تبرعات خارجية';
    case Transfer = 'تحويلات';
    case Miscellaneous = 'نثريات';
    case SmsSubscription = 'اشتراك';
    case SmsRenewal = 'تجديد';
    // A donation changed or removed after it was recorded: the entry that undoes it.
    case DonationCorrection = 'تصحيح تبرع';

    // Legacy spellings found in existing rows.
    case LegacyDonations = 'التبرعات';
    case LegacyNewSubscriptionWithDonation = 'إشتراك جديد + تبرع سنوي';
    case LegacySmsRenewal = 'تجديد اشتراك';
    case LegacyBankDeposit = 'بنك/ايداع';
    case LegacyBankWithdrawal = 'بنك/سحب';

    /** @return list<string> every stored value that means "membership subscription money". */
    public static function subscriptionValues(): array
    {
        return [self::Subscription->value, self::NewSubscription->value, self::LegacyNewSubscriptionWithDonation->value];
    }

    /** @return list<string> every stored value that means "donation money". */
    public static function donationValues(): array
    {
        return [self::Donation->value, self::LegacyDonations->value, self::AnnualDonation->value, self::OuterDonation->value];
    }

    /** @return list<string> money moved between the safe and the bank: neither income nor spending. */
    public static function transferValues(): array
    {
        return [self::Transfer->value, self::LegacyBankDeposit->value, self::LegacyBankWithdrawal->value];
    }

    /** @return list<string> */
    public static function smsValues(): array
    {
        return [self::SmsSubscription->value, self::SmsRenewal->value, self::LegacySmsRenewal->value];
    }
}
