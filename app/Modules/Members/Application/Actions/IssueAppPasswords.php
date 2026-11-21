<?php

namespace App\Modules\Members\Application\Actions;

use App\Models\Subscribers;
use App\Modules\Messaging\Application\Jobs\SendSms;
use Illuminate\Support\Facades\Hash;

/**
 * Gives members a password for the mobile app and texts it to them.
 *
 * Passwords are random. The old scheme (last 4 digits of the national ID +
 * last 4 of the mobile) could be guessed by anyone who knew both.
 */
class IssueAppPasswords
{
    public const LENGTH = 8;

    /** Members who never had a password. @return int how many got one */
    public function forMembersWithoutPassword(): int
    {
        return $this->issue(Subscribers::query()->whereNull('password'));
    }

    /**
     * Members whose current password is still the guessable one.
     *
     * @return int how many were changed (or would be, on a dry run)
     */
    public function replaceGuessablePasswords(bool $dryRun = false): int
    {
        $count = 0;

        Subscribers::query()->whereNotNull('password')->whereNotNull('ssn')->whereNotNull('mobile_no')
            ->chunkById(200, function ($members) use ($dryRun, &$count) {
                foreach ($members as $member) {
                    if (! Hash::check(self::legacyPassword($member), $member->password)) {
                        continue;
                    }
                    $count++;
                    if (! $dryRun) {
                        $this->give($member);
                    }
                }
            });

        return $count;
    }

    public static function legacyPassword(Subscribers $member): string
    {
        return substr((string) $member->ssn, -4).substr((string) $member->mobile_no, -4);
    }

    private function issue($query): int
    {
        $count = 0;
        $query->whereNotNull('ssn')->whereNotNull('mobile_no')->where('mobile_no', '!=', 0)
            ->chunkById(200, function ($members) use (&$count) {
                foreach ($members as $member) {
                    $this->give($member);
                    $count++;
                }
            });

        return $count;
    }

    private function give(Subscribers $member): void
    {
        $password = $this->randomPassword();
        $member->forceFill(['password' => Hash::make($password)])->save();
        // Revoke app sessions opened with the old password.
        $member->tokens()->delete();

        SendSms::dispatch(
            (string) $member->mobile_no,
            "كلمة المرور الخاصة بك في تطبيق جمعية أدندان: {$password}\nاسم المستخدم هو الرقم القومي.",
        );
    }

    private function randomPassword(): string
    {
        $digits = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $digits .= random_int(0, 9);
        }

        return $digits;
    }
}
