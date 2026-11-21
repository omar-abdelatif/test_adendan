<?php

namespace App\Modules\Content\Domain;

/** Reads the video id out of the YouTube link formats staff paste. */
final class YouTubeLink
{
    private const PATTERNS = [
        '~youtu\.be/([A-Za-z0-9_-]{6,})~',
        '~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})~',
        '~youtube\.com/(?:embed|shorts|live)/([A-Za-z0-9_-]{6,})~',
    ];

    public static function videoId(?string $url): ?string
    {
        foreach (self::PATTERNS as $pattern) {
            if ($url && preg_match($pattern, $url, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    public static function isValid(?string $url): bool
    {
        return self::videoId($url) !== null;
    }
}
