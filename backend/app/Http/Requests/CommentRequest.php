<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Services\RecaptchaService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where(fn ($query) => $query->whereNull('parent_id')),
            ],
            'replied_to_comment_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    $target = Comment::whereKey($value)->whereNotNull('parent_id')->first();

                    if (! $target || (string) $target->parent_id !== (string) $this->input('parent_id')) {
                        $fail('The selected reply to address is invalid.');
                    }
                },
            ],
            'user_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+$/'],
            'email' => ['required', 'string', 'email'],
            'home_page' => ['nullable', 'string', 'url'],
            'text' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    $length = mb_strlen(trim(strip_tags($value)));

                    if ($length < 1) {
                        $fail('The text is required.');
                    }

                    if ($length > 1000) {
                        $fail('The text may not be greater than 1000 characters.');
                    }
                },
            ],
            'image' => ['nullable', 'array'],
            'image.original' => ['required_with:image', 'string', $this->decodableImageRule()],
            'image.cropped' => ['required_with:image', 'string', $this->decodableImageRule()],
            'recaptcha_token' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! app(RecaptchaService::class)->verify($value, $this->ip())) {
                        $fail('reCAPTCHA verification failed. Please try again.');
                    }
                },
            ],
        ];
    }

    private function decodableImageRule(): Closure {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value)) {
                return;
            }

            $encoded = str_contains($value, ',') ? substr($value, strpos($value, ',') + 1) : $value;
            $decoded = base64_decode($encoded, true);

            if ($decoded === false || @imagecreatefromstring($decoded) === false) {
                $fail('The image must be a valid PNG, JPEG, GIF, or WebP file (SVG and other vector formats are not supported).');
            }
        };
    }
}
