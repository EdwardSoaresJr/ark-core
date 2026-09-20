<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfilePartsTechUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'partstech_username' => ['nullable', 'string', 'max:128'],
            'partstech_password' => ['nullable', 'string', 'max:512'],
        ];
    }
}
