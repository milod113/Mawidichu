<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signal temps réel (Reverb) sur le canal privé d'un service : les écrans
 * ouverts rechargent leurs données. `type` : quota | file | controle.
 */
class ServiceActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $serviceId,
        public string $type,
        public ?string $date = null,
    ) {}

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
        return [new PrivateChannel('service.'.$this->serviceId)];
    }

    public function broadcastAs(): string
    {
        return 'service.activity';
    }
}
