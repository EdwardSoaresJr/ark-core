<?php

namespace App\Http\Requests\Growth;

use Illuminate\Foundation\Http\FormRequest;

class DiscoverGrowthIntegrationsLocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('growth.access') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'growth_google_service_account_json' => ['nullable', 'string'],
        ];
    }
}
