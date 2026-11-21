<?php

namespace Tests\Fakes;

use App\Modules\Messaging\Domain\SmsSender;

/** Records messages instead of calling the SMS provider. */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{number: string, message: string}> */
    public array $sent = [];

    public function __construct(public float $balance = 1000) {}

    public function send(array $numbers, string $message): array
    {
        return array_map(function ($number) use ($message) {
            $this->sent[] = ['number' => (string) $number, 'message' => $message];

            return ['number' => (string) $number, 'success' => true, 'status' => 200];
        }, $numbers);
    }

    public function balance(): float
    {
        return $this->balance;
    }

    /** @return list<string> */
    public function numbers(): array
    {
        return array_column($this->sent, 'number');
    }
}
