<?php

namespace Tests\Feature;

use App\Events\ServiceActivity;
use Tests\TestCase;

class RealtimeResilienceTest extends TestCase
{
    public function test_unreachable_realtime_server_never_breaks_a_business_action(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
        ]);

        ServiceActivity::emit(1, 'file', today()->toDateString());

        $this->assertTrue(true); // aucune exception n'a remonté
    }
}
