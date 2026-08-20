<?php

namespace Tests\Feature;

use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaServiceTest extends TestCase
{
    public function test_verify_returns_true_when_google_reports_success(): void {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(app(RecaptchaService::class)->verify('token'));
    }

    public function test_verify_returns_false_when_google_reports_failure(): void {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        $this->assertFalse(app(RecaptchaService::class)->verify('token'));
    }

    public function test_verify_returns_false_when_the_request_itself_fails(): void {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(null, 500),
        ]);

        $this->assertFalse(app(RecaptchaService::class)->verify('token'));
    }

    public function test_verify_sends_the_token_secret_and_ip(): void {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        app(RecaptchaService::class)->verify('the-token', '203.0.113.1');

        Http::assertSent(fn ($request) => $request['response'] === 'the-token'
            && $request['remoteip'] === '203.0.113.1'
            && $request['secret'] === config('services.recaptcha.secret_key'));
    }
}
