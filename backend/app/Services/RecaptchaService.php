<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(string $token, ?string $ip = null): bool {
        try {
            $response = Http::asForm()->post(self::VERIFY_URL, [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $response->json('success', false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
