<?php

namespace App\Http\Requests;

use App\Ark\Runtime\Preferences\AccentColor;
use App\Ark\Runtime\Preferences\AccentTheme;
use App\Ark\Runtime\Preferences\DisplayTheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileAppearanceUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('accent_theme') !== AccentTheme::Custom->value) {
            $this->merge(['accent_color' => null]);

            return;
        }

        $normalized = AccentColor::normalize($this->input('accent_color'));

        if ($normalized !== null) {
            $this->merge(['accent_color' => $normalized]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accent_theme' => ['required', Rule::in(AccentTheme::values())],
            'accent_color' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('accent_theme') === AccentTheme::Custom->value),
                'regex:/^#[0-9a-f]{6}$/',
            ],
            'display_theme' => ['required', Rule::in(DisplayTheme::values())],
        ];
    }
}
