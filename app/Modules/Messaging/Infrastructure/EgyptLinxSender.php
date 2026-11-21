<?php

namespace App\Modules\Messaging\Infrastructure;

use App\Modules\Messaging\Domain\SmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** EgyptLinx bulk SMS HTTP API. */
class EgyptLinxSender implements SmsSender
{
    private const SEND_URL = 'https://bulk.egypt-linx.com/api';

    private const BALANCE_URL = 'https://bulk.egypt-linx.com/Balance';

    /** @param array{provider?: ?string, password?: ?string, sender?: ?string} $config */
    public function __construct(private readonly array $config) {}

    public function send(array $numbers, string $message): array
    {
        $results = [];

        foreach ($numbers as $number) {
            $number = $this->normalize((string) $number);
            try {
                $response = Http::timeout(15)->get(self::SEND_URL, [
                    'Provider' => $this->config['provider'] ?? '',
                    'Password' => $this->config['password'] ?? '',
                    'Sender' => $this->config['sender'] ?? '',
                    'Number' => $number,
                    'Binary' => '00',
                    'Code' => $message,
                ]);
                $results[] = ['number' => $number, 'success' => $response->successful(), 'status' => $response->status()];
            } catch (Throwable $e) {
                Log::warning('SMS send failed', ['number' => substr($number, -4), 'error' => $e->getMessage()]);
                $results[] = ['number' => $number, 'success' => false, 'status' => 0];
            }
        }

        return $results;
    }

    public function balance(): float
    {
        try {
            $response = Http::timeout(15)->get(self::BALANCE_URL, [
                'Provider' => $this->config['provider'] ?? '',
                'Password' => $this->config['password'] ?? '',
            ]);
        } catch (Throwable) {
            return 0;
        }

        return $response->successful() ? (float) $response->body() : 0;
    }

    /** Numbers are stored without the leading 0 (bigint columns). */
    private function normalize(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        return str_starts_with($digits, '0') ? $digits : '0'.$digits;
    }
}
