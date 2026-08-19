<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentFilterRequest extends FormRequest
{
    private const SORT_KEYS = ['newest', 'oldest', 'user_name_asc', 'user_name_desc', 'email_asc', 'email_desc'];

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'sort_by' => ['sometimes', Rule::in(self::SORT_KEYS)],
        ];
    }

    public function filters(): array {
        return [
            'sort_by' => $this->input('sort_by', self::SORT_KEYS[0]),
        ];
    }
}
