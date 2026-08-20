<?php

namespace Tests\Feature\Concerns;

use App\Services\RabbitMQService;

trait FakesRabbitMQService
{
    protected function fakeRabbitMQService(): object {
        $fake = new class extends RabbitMQService {
            public array $published = [];

            public function publish(string $queue, array $payload): void {
                $this->published[$queue][] = $payload;
            }
        };

        $this->app->instance(RabbitMQService::class, $fake);

        return $fake;
    }
}
