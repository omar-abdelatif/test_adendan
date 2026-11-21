<?php

namespace App\Modules\Messaging\Domain;

/** Push notifications to the members' mobile app. Firebase is the production implementation. */
interface PushNotifier
{
    /**
     * @param  array<string, string>  $data
     * @return bool false when the device token is no longer valid
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): bool;
}
