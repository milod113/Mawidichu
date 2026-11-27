<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Appel d'un patient en salle d'attente : consommé par l'écran TV.
 */
class PatientCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $appel
     */
    public function __construct(public array $appel) {}

    /**
     * Diffuse sans jamais bloquer l'action métier : si le serveur temps réel (Reverb) est
     * arrêté, l'erreur est journalisée et les écrans se rafraîchissent par sondage.
     */
    public static function emit(mixed ...$args): void
    {
        try {
            static::dispatch(...$args);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tv')];
    }

    public function broadcastAs(): string
    {
        return 'patient.called';
    }
}
