<?php

namespace App\Modules\Messaging\Application\Jobs;

use App\Modules\Messaging\Domain\SmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one SMS through the queue so a slow provider never blocks a web request.
 * Runs inline while QUEUE_CONNECTION=sync.
 */
class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $number, public readonly string $message) {}

    public function handle(SmsSender $sender): void
    {
        $sender->send([$this->number], $this->message);
    }
}
