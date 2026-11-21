<?php

namespace App\Modules\Messaging\Infrastructure;

use App\Modules\Messaging\Domain\PushNotifier;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Firebase Cloud Messaging HTTP v1 API with a service-account key. */
class FcmPushNotifier implements PushNotifier
{
    /** @param array{credentials?: ?string, project_id?: ?string} $config */
    public function __construct(private readonly array $config) {}

    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->config['project_id']}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'android' => [
                        'priority' => 'high',
                        'notification' => ['notification_priority' => 'PRIORITY_HIGH', 'default_sound' => true, 'default_vibrate_timings' => true],
                    ],
                    'data' => $data,
                ],
            ]);

        if ($response->status() === 404 && $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
            return false;
        }
        if (! $response->successful()) {
            Log::warning('FCM push failed', ['status' => $response->status(), 'token' => substr($deviceToken, 0, 12).'…']);
        }

        return true;
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase_access_token', now()->addMinutes(50), function () {
            $client = new GoogleClient;
            $client->setAuthConfig(json_decode((string) file_get_contents((string) $this->config['credentials']), true));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();

            return $client->getAccessToken()['access_token'];
        });
    }
}
