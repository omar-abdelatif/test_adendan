<?php

namespace App\Modules\Messaging\Domain;

/**
 * How many SMS parts an Arabic (UCS-2) message takes: 70 characters for a
 * single message, 67 per part once it is split.
 */
final class SmsLength
{
    public static function parts(string $message): int
    {
        $length = mb_strlen($message);

        return $length <= 70 ? 1 : (int) ceil($length / 67);
    }
}
