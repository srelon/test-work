<?php

namespace Tests\Feature\Concerns;

use App\Services\RecaptchaService;

trait FakesRecaptcha
{
    protected function fakeRecaptcha(bool $success = true): void {
        $fake = new class($success) extends RecaptchaService
        {
            public function __construct(private bool $success) {}

            public function verify(string $token, ?string $ip = null): bool {
                return $this->success;
            }
        };

        $this->app->instance(RecaptchaService::class, $fake);
    }
}
