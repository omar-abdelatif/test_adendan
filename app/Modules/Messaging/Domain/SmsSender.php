<?php

namespace App\Modules\Messaging\Domain;

/** Sends Arabic text messages. EgyptLinx is the production implementation. */
interface SmsSender
{
    /**
     * @param  list<string|int>  $numbers  Egyptian mobile numbers, with or without the leading 0
     * @return array<int, array{number: string, success: bool, status: int}>
     */
    public function send(array $numbers, string $message): array;

    /** Messages left in the provider account. */
    public function balance(): float;
}
