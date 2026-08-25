<?php

namespace Tests\Unit;

use App\Http\Requests\CommentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Concerns\FakesRecaptcha;
use Tests\TestCase;

class CommentRequestTest extends TestCase
{
    use FakesRecaptcha;

    public function test_comment_request_rejects_invalid_data_and_accepts_a_valid_submission(): void {
        $this->fakeRecaptcha();

        $rules = (new CommentRequest())->rules();

        $invalid = Validator::make([
            'user_name' => 'John Doe!',
            'email' => 'not-an-email',
            'home_page' => 'not-a-url',
            'text' => '',
            'recaptcha_token' => 'test-token',
        ], $rules);

        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('user_name', $invalid->errors()->toArray());
        $this->assertArrayHasKey('email', $invalid->errors()->toArray());
        $this->assertArrayHasKey('home_page', $invalid->errors()->toArray());
        $this->assertArrayHasKey('text', $invalid->errors()->toArray());

        $valid = Validator::make([
            'user_name' => 'JohnDoe123',
            'email' => 'john@example.com',
            'home_page' => 'https://example.com',
            'text' => 'Hello world',
            'recaptcha_token' => 'test-token',
        ], $rules);

        $this->assertTrue($valid->passes());
    }
}
