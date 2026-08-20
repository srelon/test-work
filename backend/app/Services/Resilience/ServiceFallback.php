<?php

namespace App\Services\Resilience;

use Closure;
use Throwable;

class ServiceFallback
{
    public function __construct(private ServiceHealth $health) {}

    public function attempt(string $service, Closure $primary, Closure $fallback): mixed {
        if ($this->health->shouldTry($service)) {
            try {
                $value = $primary();
                $this->health->markUp($service);

                return $value;
            } catch (Throwable $e) {
                report($e);
                $this->health->markDown($service);
            }
        }

        return $fallback();
    }
}
