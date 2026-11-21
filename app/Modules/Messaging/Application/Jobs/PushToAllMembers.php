<?php

namespace App\Modules\Messaging\Application\Jobs;

use App\Models\Subscribers;
use App\Modules\Messaging\Domain\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends one push notification to every member with the app installed.
 * Queued so publishing news does not wait for hundreds of HTTP calls; tokens
 * Firebase reports as unregistered are cleared.
 */
class PushToAllMembers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, string> $data */
    public function __construct(public readonly string $title, public readonly string $body, public readonly array $data = []) {}

    public function handle(PushNotifier $push): void
    {
        Subscribers::query()->whereNotNull('fcm_token')->select(['id', 'fcm_token'])
            ->chunkById(200, function ($members) use ($push) {
                foreach ($members as $member) {
                    try {
                        if (! $push->send($member->fcm_token, $this->title, $this->body, $this->data)) {
                            $member->forceFill(['fcm_token' => null])->save();
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
