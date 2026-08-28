<?php

namespace App\Http\Requests\Growth;

use App\Ark\Growth\Integrations\Google\GoogleServiceAccountCredentials;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGrowthIntegrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('growth.access') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'public_sitemap_enabled' => $this->checkboxBoolean('public_sitemap_enabled'),
            'google_business_profile_enabled' => $this->checkboxBoolean('google_business_profile_enabled'),
            'backfill_after_save' => $this->checkboxBoolean('backfill_after_save'),
            'google_search_console_enabled' => $this->checkboxBoolean('google_search_console_enabled'),
            'google_indexing_enabled' => $this->checkboxBoolean('google_indexing_enabled'),
            'seo_automation_enabled' => $this->checkboxBoolean('seo_automation_enabled'),
            'use_server_firebase_service_account' => $this->checkboxBoolean('use_server_firebase_service_account'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'public_sitemap_enabled' => ['required', 'boolean'],
            'google_business_profile_enabled' => ['required', 'boolean'],
            'google_business_profile_location' => ['nullable', 'string', 'max:255'],
            'growth_google_service_account_json' => ['nullable', 'string'],
            'use_server_firebase_service_account' => ['required', 'boolean'],
            'backfill_after_save' => ['required', 'boolean'],
            'google_search_console_enabled' => ['required', 'boolean'],
            'google_search_console_property' => ['nullable', 'string', 'max:255'],
            'google_indexing_enabled' => ['required', 'boolean'],
            'seo_automation_enabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $enabled = $this->boolean('google_business_profile_enabled');
            $location = trim((string) $this->input('google_business_profile_location', ''));
            $json = trim((string) $this->input('growth_google_service_account_json', ''));
            $hasStoredCredentials = GrowthIntegrationSettings::current()->hasGoogleServiceAccountCredentials();

            if ($enabled && $location === '') {
                $validator->errors()->add(
                    'google_business_profile_location',
                    'Pick a listing before enabling sync — click Discover locations and choose Lugs N Plugs, or paste the locations/… ID here.',
                );
            }

            if ($json !== '') {
                try {
                    GoogleServiceAccountCredentials::parse($json);
                } catch (\InvalidArgumentException $exception) {
                    $validator->errors()->add(
                        'growth_google_service_account_json',
                        $exception->getMessage(),
                    );
                }

                return;
            }

            $useServerFirebase = $this->boolean('use_server_firebase_service_account');
            $hasServerFirebase = GrowthIntegrationSettings::current()->serverGoogleServiceAccountClientEmail() !== null;

            if ($enabled && ! $hasStoredCredentials && ! ($useServerFirebase && $hasServerFirebase)) {
                $validator->errors()->add(
                    'growth_google_service_account_json',
                    'Paste the Google service account JSON to enable Google Business Profile.',
                );
            }
        });
    }

    public function shouldBackfillAfterSave(): bool
    {
        return $this->boolean('backfill_after_save');
    }

    private function checkboxBoolean(string $key): bool
    {
        $value = $this->input($key);

        if (is_array($value)) {
            foreach ($value as $item) {
                if (filter_var($item, FILTER_VALIDATE_BOOL)) {
                    return true;
                }
            }

            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
