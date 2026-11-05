<?php

namespace App\Ark\Operations\Booking\Http;

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentRequestException;
use App\Ark\Operations\Booking\BookingLocationMode;
use App\Ark\Operations\Booking\BookingMode;
use App\Ark\Operations\Booking\BookingQuestion;
use App\Ark\Operations\Booking\BookingQuestionType;
use App\Ark\Operations\Booking\BookingService;
use App\Ark\Operations\Booking\BookingSettings;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Settings → Online booking: what the shop's customers see and may choose when they book.
 */
final class BookingSettingsController
{
    public const TABS = ['form', 'services', 'questions', 'availability', 'website'];

    public function edit(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'form';
        $settings = ShopSettings::current();

        return view('operations.settings.booking', [
            'tab' => $tab,
            'booking' => BookingSettings::forShop($settings),
            'requestAvailability' => AppointmentRequestAvailability::forShop($settings),
            'services' => BookingService::query()->active()->ordered()->get(),
            'questions' => BookingQuestion::query()->active()->get(),
            'exceptions' => AppointmentRequestException::query()
                ->whereDate('date', '>=', ShopDisplayTimezone::now()->toDateString())
                ->orderBy('date')
                ->limit(60)
                ->get(),
            'bookingUrl' => route('public.book'),
            'popupUrl' => route('public.book.popup'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = (string) $request->input('section', 'form');
        $current = BookingSettings::forShop();

        $data = $request->validate([
            'section' => ['required', Rule::in(['form', 'availability', 'website'])],
            'enabled' => ['nullable', 'boolean'],
            'mode' => ['nullable', Rule::enum(BookingMode::class)],
            'title' => ['nullable', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'request_confirmation' => ['nullable', 'string', 'max:1000'],
            'instant_confirmation' => ['nullable', 'string', 'max:1000'],
            'concern_choices' => ['nullable', 'string', 'max:2000'],
            'fields.email' => ['nullable', Rule::in(BookingSettings::FIELD_LEVELS)],
            'fields.vehicle' => ['nullable', Rule::in(BookingSettings::FIELD_LEVELS)],
            'fields.concern' => ['nullable', Rule::in([BookingSettings::FIELD_OPTIONAL, BookingSettings::FIELD_REQUIRED])],
            'fields.vin' => ['nullable', 'boolean'],
            'fields.contact_preference' => ['nullable', 'boolean'],
            'fields.drivability' => ['nullable', 'boolean'],
            'max_vehicles' => ['nullable', 'integer', 'min:1', 'max:'.BookingSettings::MAX_VEHICLES],
            'service_area_postal_codes' => ['nullable', 'string', 'max:6000'],
            'service_area_message' => ['nullable', 'string', 'max:300'],
            'daily_requests' => ['nullable', 'integer', 'min:1', 'max:500'],
            'respect_capacity' => ['nullable', 'boolean'],
            'concurrent_appointments' => ['nullable', 'integer', 'min:1', 'max:50'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'floating_button' => ['nullable', 'boolean'],
            'allowed_domains' => ['nullable', 'string', 'max:2000'],
        ]);

        $next = $current;
        if ($section === 'form') {
            $next['enabled'] = $request->boolean('enabled');
            $next['mode'] = $data['mode'] ?? $current['mode'];
            foreach (['title', 'intro', 'instructions', 'request_confirmation', 'instant_confirmation'] as $key) {
                $next[$key] = (string) ($data[$key] ?? '');
            }
            $next['concern_choices'] = preg_split('/\r?\n/', (string) ($data['concern_choices'] ?? '')) ?: [];
            $next['fields'] = [
                'email' => $data['fields']['email'] ?? $current['fields']['email'],
                'vehicle' => $data['fields']['vehicle'] ?? $current['fields']['vehicle'],
                'concern' => $data['fields']['concern'] ?? $current['fields']['concern'],
                'vin' => $request->boolean('fields.vin'),
                'contact_preference' => $request->boolean('fields.contact_preference'),
                'drivability' => $request->boolean('fields.drivability'),
            ];
            $next['max_vehicles'] = (int) ($data['max_vehicles'] ?? 1);
        } elseif ($section === 'availability') {
            $next['limits'] = [
                'daily_requests' => $data['daily_requests'] ?? null,
                'respect_capacity' => $request->boolean('respect_capacity'),
                'concurrent_appointments' => $data['concurrent_appointments'] ?? null,
            ];
            $next['service_area'] = [
                'postal_codes' => BookingSettings::postalCodes((string) ($data['service_area_postal_codes'] ?? '')),
                'message' => (string) ($data['service_area_message'] ?? ''),
            ];
        } else {
            $next['popup'] = [
                'button_label' => (string) ($data['button_label'] ?? ''),
                'floating_button' => $request->boolean('floating_button'),
                'allowed_domains' => BookingSettings::domains((string) ($data['allowed_domains'] ?? '')),
            ];
        }

        ShopSettings::current()->forceFill(['online_booking' => BookingSettings::normalize($next)])->save();
        ShopSettings::forgetCurrent();

        return redirect()->route('operations.settings.booking', ['tab' => $section])->with('status', 'Online booking saved.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $this->validatedService($request);
        $data['position'] = (int) BookingService::query()->max('position') + 1;
        BookingService::query()->create($data);

        return redirect()->route('operations.settings.booking', ['tab' => 'services'])->with('status', 'Service added.');
    }

    public function updateService(Request $request, BookingService $service): RedirectResponse
    {
        $service->update($this->validatedService($request, $service));

        return redirect()->route('operations.settings.booking', ['tab' => 'services'])->with('status', $service->name.' saved.');
    }

    public function destroyService(BookingService $service): RedirectResponse
    {
        $service->forceFill(['archived_at' => now()])->save();

        return redirect()->route('operations.settings.booking', ['tab' => 'services'])->with('status', $service->name.' removed from online booking. Past requests keep it.');
    }

    public function moveService(Request $request, BookingService $service): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? -1 : 1;
        DB::transaction(function () use ($service, $direction): void {
            $ordered = BookingService::query()->active()->ordered()->get()->values();
            $index = $ordered->search(fn (BookingService $s): bool => $s->id === $service->id);
            $swap = $ordered->get($index + $direction);
            if ($index === false || $swap === null) {
                return;
            }
            foreach ($ordered as $position => $row) {
                $row->forceFill(['position' => $position])->save();
            }
            $service->forceFill(['position' => $index + $direction])->save();
            $swap->forceFill(['position' => $index])->save();
        });

        return redirect()->route('operations.settings.booking', ['tab' => 'services']);
    }

    public function seedServices(): RedirectResponse
    {
        if (BookingService::query()->active()->exists()) {
            return redirect()->route('operations.settings.booking', ['tab' => 'services']);
        }

        $defaults = [
            ['name' => 'Diagnostics', 'description' => 'Check engine light, warning lights, or something not right.', 'duration_minutes' => 60, 'options' => null],
            ['name' => 'Brakes', 'description' => 'Brake inspection and repair.', 'duration_minutes' => 90, 'options' => [['label' => 'Front'], ['label' => 'Rear'], ['label' => 'Both'], ['label' => 'Not sure']]],
            ['name' => 'Oil change', 'description' => 'Engine oil and filter.', 'duration_minutes' => 30, 'options' => [['label' => 'Conventional'], ['label' => 'Synthetic'], ['label' => 'High mileage']]],
            ['name' => 'Steering and suspension', 'description' => 'Alignment, noises, pulling, worn parts.', 'duration_minutes' => 90, 'options' => null],
        ];

        foreach ($defaults as $position => $row) {
            BookingService::query()->create([...$row, 'position' => $position, 'location_mode' => BookingLocationMode::Shop]);
        }

        return redirect()->route('operations.settings.booking', ['tab' => 'services'])->with('status', 'Starter services added. Edit them to match your shop.');
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $data = $this->validatedQuestion($request);
        $data['position'] = (int) BookingQuestion::query()->max('position') + 1;
        BookingQuestion::query()->create($data);

        return redirect()->route('operations.settings.booking', ['tab' => 'questions'])->with('status', 'Question added.');
    }

    public function updateQuestion(Request $request, BookingQuestion $question): RedirectResponse
    {
        $question->update($this->validatedQuestion($request));

        return redirect()->route('operations.settings.booking', ['tab' => 'questions'])->with('status', 'Question saved.');
    }

    public function destroyQuestion(BookingQuestion $question): RedirectResponse
    {
        $question->forceFill(['archived_at' => now()])->save();

        return redirect()->route('operations.settings.booking', ['tab' => 'questions'])->with('status', 'Question removed. Answers already received stay on their requests.');
    }

    public function storeClosedDate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        AppointmentRequestException::query()->updateOrCreate(
            ['date' => $data['date']],
            [
                'mode' => AppointmentRequestException::MODE_DISABLE,
                'reason' => $data['reason'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ],
        );

        return redirect()->route('operations.settings.booking', ['tab' => 'availability'])->with('status', 'Online booking closed on '.$data['date'].'.');
    }

    public function destroyClosedDate(AppointmentRequestException $exception): RedirectResponse
    {
        $exception->delete();

        return redirect()->route('operations.settings.booking', ['tab' => 'availability'])->with('status', 'Date override removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedService(Request $request, ?BookingService $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'labor_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24'],
            'concern_prompt' => ['nullable', 'string', 'max:500'],
            'location_mode' => ['required', Rule::enum(BookingLocationMode::class)],
            'requires_approval' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*.label' => ['nullable', 'string', 'max:120'],
            'options.*.duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'options.*.requires_details' => ['nullable', 'boolean'],
        ]);

        $options = [];
        foreach ($data['options'] ?? [] as $option) {
            $label = trim((string) ($option['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $options[] = [
                'label' => $label,
                'duration_minutes' => isset($option['duration_minutes']) && $option['duration_minutes'] !== '' ? (int) $option['duration_minutes'] : null,
                'requires_details' => filter_var($option['requires_details'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return [
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'labor_hours' => filled($data['labor_hours'] ?? null) ? (float) $data['labor_hours'] : null,
            'concern_prompt' => filled($data['concern_prompt'] ?? null) ? trim($data['concern_prompt']) : null,
            'location_mode' => $data['location_mode'],
            'requires_approval' => $request->boolean('requires_approval'),
            'is_public' => $request->boolean('is_public'),
            'options' => $options !== [] ? $options : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedQuestion(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'help' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(BookingQuestionType::class)],
            'choices' => ['nullable', 'string', 'max:2000'],
            'required' => ['nullable', 'boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('booking_services', 'id')],
        ]);

        $choices = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($data['choices'] ?? '')) ?: [])));
        if ($data['type'] === BookingQuestionType::Choice->value && count($choices) < 2) {
            throw ValidationException::withMessages(['choices' => 'Give at least two choices, one per line.']);
        }

        return [
            'label' => trim($data['label']),
            'help' => filled($data['help'] ?? null) ? trim($data['help']) : null,
            'type' => $data['type'],
            'choices' => $data['type'] === BookingQuestionType::Choice->value ? array_slice($choices, 0, 20) : null,
            'required' => $request->boolean('required'),
            'service_ids' => array_values(array_map('intval', $data['service_ids'] ?? [])) ?: null,
        ];
    }
}
