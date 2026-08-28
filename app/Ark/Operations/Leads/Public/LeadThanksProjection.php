<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Customer\CustomerSurfaceUrls;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Auth;

final class LeadThanksProjection
{
    /**
     * @return list<string>
     */
    private const NEXT_STEPS = [
        'We received your request.',
        'An advisor reads what you sent.',
        'We call or text if we need more details.',
        'We agree on the next step together.',
    ];

    /**
     * @return list<string>
     */
    private const APPOINTMENT_REQUEST_NEXT_STEPS = [
        'We’ll review your request.',
        'We’ll check availability.',
        'We’ll contact you to confirm.',
    ];

    /**
     * @var list<array{keywords: list<string>, slug: string}>
     */
    private const COMMON_PROBLEM_HINTS = [
        ['keywords' => ['brake', 'squeal', 'grinding'], 'slug' => 'brake-noise'],
        ['keywords' => ['check engine', 'cel', 'engine light'], 'slug' => 'check-engine-light'],
        ['keywords' => ['won\'t start', 'wont start', 'no start', 'dead battery', 'crank'], 'slug' => 'car-wont-start'],
        ['keywords' => ['overheat', 'over heating', 'temperature'], 'slug' => 'engine-overheating'],
        ['keywords' => ['ac ', 'a/c', 'air conditioning', 'not cold', 'blowing warm'], 'slug' => 'ac-not-cold'],
        ['keywords' => ['battery', 'dies overnight', 'dead again'], 'slug' => 'battery-keeps-dying'],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly array $data,
    ) {}

    public static function forLead(?Lead $lead): self
    {
        $shop = ShopSettings::current();
        $publicSurface = PublicSurfaceSettings::current();
        $shopName = $shop->displayName();
        $phoneDisplay = PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
        $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';

        $concern = filled($lead?->concern) ? trim((string) $lead->concern) : null;
        $relatedProblem = $concern !== null ? self::relatedCommonProblem($concern) : null;
        $appointmentRequest = $lead instanceof Lead && PublicAppointmentRequest::isBookSurfaceFromLead($lead);
        $preferredAvailability = $appointmentRequest
            ? trim((string) data_get($lead->metadata, 'appointment_request.preferred_availability', ''))
            : '';

        return new self([
            'has_lead' => $lead instanceof Lead,
            'appointment_request' => $appointmentRequest,
            'first_name' => self::firstName($lead?->contact_name),
            'shop_name' => $shopName,
            'vehicle_label' => $lead?->roughVehicleLabel(),
            'concern' => $concern,
            'preferred_availability' => $preferredAvailability !== '' ? $preferredAvailability : null,
            'status_eyebrow' => $appointmentRequest ? 'Appointment Requested' : 'Request received',
            'summary_line' => $appointmentRequest
                ? 'We’ll review your request, check availability, and contact you to confirm.'
                : sprintf(
                    'Your concern was sent to %s. We’ll review it and reach out with the next step.',
                    $shopName,
                ),
            'response_time_hint' => $publicSurface['response_time_hint'],
            'business_hours_label' => $publicSurface['business_hours_label'],
            'local_tagline' => $publicSurface['local_tagline'],
            'personality_line' => $appointmentRequest
                ? 'Done.'
                : 'A real advisor will follow up — not an automated reply.',
            'next_steps' => $appointmentRequest ? self::APPOINTMENT_REQUEST_NEXT_STEPS : self::NEXT_STEPS,
            'while_you_wait' => self::whileYouWaitLinks($relatedProblem, $phoneTel),
            'related_common_problem' => $relatedProblem,
            'shop_photos' => array_slice(PublicSurfaceSettings::photosForDisplay(), 0, 2),
            'phone_display' => $phoneDisplay,
            'phone_tel' => $phoneTel,
            'sms_href' => 'sms:'.$phoneTel,
            'social_links' => ShopSocialProfiles::forDisplay($publicSurface),
        ]);
    }

    /**
     * @return list<array{label: string, href: string, description: string}>
     */
    private static function whileYouWaitLinks(?array $relatedProblem, string $phoneTel): array
    {
        $links = [];

        if (Auth::guard('portal')->check()) {
            $links[] = [
                'label' => 'Your vehicles',
                'href' => CustomerSurfaceUrls::portalHome(),
                'description' => 'See service history and records already on file.',
            ];
        } else {
            $links[] = [
                'label' => 'View your vehicle records',
                'href' => CustomerSurfaceUrls::portalAccess(),
                'description' => 'Sign in with your phone to see past visits and vehicles.',
            ];
        }

        if ($relatedProblem !== null) {
            $links[] = [
                'label' => 'Learn about '.$relatedProblem['title'],
                'href' => $relatedProblem['href'],
                'description' => 'What it usually means and what to expect at the shop.',
            ];
        } else {
            $links[] = [
                'label' => 'Browse common car problems',
                'href' => CustomerSurfaceUrls::commonProblems(),
                'description' => 'Plain guides for problems we see in Colorado Springs.',
            ];
        }

        $links[] = [
            'label' => 'Text us photos or video',
            'href' => 'sms:'.$phoneTel,
            'description' => 'A picture of the dash, noise, or leak helps us prepare.',
        ];

        return $links;
    }

    /**
     * @return array{title: string, href: string}|null
     */
    private static function relatedCommonProblem(string $concern): ?array
    {
        $haystack = strtolower($concern);

        foreach (self::COMMON_PROBLEM_HINTS as $hint) {
            foreach ($hint['keywords'] as $keyword) {
                if (! str_contains($haystack, strtolower($keyword))) {
                    continue;
                }

                $problem = CommonProblemRegistry::find($hint['slug']);

                if ($problem === null) {
                    continue;
                }

                return [
                    'title' => (string) $problem['title'],
                    'href' => route('public.common-problems.show', $problem['slug']),
                ];
            }
        }

        return null;
    }

    private static function firstName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return strtok($name, ' ') ?: null;
    }
}
