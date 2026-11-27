<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Réservation atomique temporaire (60 s par défaut) d'une place pendant que le
 * guichet choisit une date : empêche deux guichets de se disputer la dernière
 * place. Quota / vagues : on compte les réservations concurrentes ; créneau
 * dynamique : un seul détenteur par créneau.
 */
class SlotLockService
{
    public function ttl(): int
    {
        return config('mawid.verrou_secondes');
    }

    /**
     * @param  int  $available  places encore réservables (hors verrous des autres)
     */
    public function acquire(int $planningId, string $date, ?string $slot, int $userId, int $available): bool
    {
        $key = $this->key($planningId, $date, $slot);

        try {
            return Cache::lock('mgr:'.$key, 5)->block(5, function () use ($key, $slot, $userId, $available) {
                $holders = $this->holders($key);
                $others = count(array_diff_key($holders, [$userId => true]));

                if ($slot !== null ? $others > 0 : ($available - $others) <= 0) {
                    return false;
                }

                $holders[$userId] = now()->getTimestamp() + $this->ttl();
                Cache::put('holders:'.$key, $holders, $this->ttl() * 2);

                return true;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    /**
     * Nombre de réservations actives d'autres utilisateurs sur cette place.
     */
    public function others(int $planningId, string $date, ?string $slot, int $userId): int
    {
        return count(array_diff_key($this->holders($this->key($planningId, $date, $slot)), [$userId => true]));
    }

    public function release(int $planningId, string $date, ?string $slot, int $userId): void
    {
        $key = $this->key($planningId, $date, $slot);
        $holders = $this->holders($key);
        unset($holders[$userId]);
        Cache::put('holders:'.$key, $holders, $this->ttl() * 2);
    }

    private function key(int $planningId, string $date, ?string $slot): string
    {
        return "$planningId:$date:".($slot ?? 'q');
    }

    /**
     * @return array<int, int> utilisateur => expiration (timestamp)
     */
    private function holders(string $key): array
    {
        $now = now()->getTimestamp();

        return array_filter(Cache::get('holders:'.$key, []), fn (int $expire) => $expire > $now);
    }
}
