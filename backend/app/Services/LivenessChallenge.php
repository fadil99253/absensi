<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LivenessChallenge
{
    public function issue(int $userId): array
    {
        $id = Str::random(48);
        $actions = ['blink', 'head_turn'];

        Cache::put($this->key($id), [
            'user_id' => $userId,
            'actions' => $actions,
            'issued_at' => now()->timestamp,
        ], now()->addMinutes(2));

        return [
            'challenge_id' => $id,
            'actions' => $actions,
            'expires_in_seconds' => 120,
        ];
    }

    public function consume(string $id): ?array
    {
        return Cache::pull($this->key($id));
    }

    private function key(string $id): string
    {
        return 'hadirku:liveness:'.hash('sha256', $id);
    }
}
