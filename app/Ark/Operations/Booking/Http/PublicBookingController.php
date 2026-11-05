<?php

namespace App\Ark\Operations\Booking\Http;

use App\Ark\Customer\Theme\CustomerThemes;
use App\Ark\Operations\Booking\AppointmentRequest;
use App\Ark\Operations\Booking\BookingAvailability;
use App\Ark\Operations\Booking\BookingContext;
use App\Ark\Operations\Booking\BookingFormProjection;
use App\Ark\Operations\Booking\BookingIdentity;
use App\Ark\Operations\Booking\BookingMode;
use App\Ark\Operations\Booking\BookingService;
use App\Ark\Operations\Booking\BookingSettings;
use App\Ark\Operations\Booking\SubmitBooking;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\LeadFormRenderStamp;
use App\Ark\Operations\Leads\LeadIngressContext;
use App\Ark\Operations\Leads\LeadIngressHygiene;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Measurement\AppointmentRequestConversion;
use App\Ark\Operations\Leads\Public\PublicBookWizardConcerns;
use App\Ark\Website\PublishedWebsite;
use App\Ark\Website\PublishedWebsiteResolver;
use App\Ark\Website\WebsiteHosts;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

/**
 * The customer's booking form: full page at /book, chrome-less inside the website popup (?embed=1).
 *
 * It keeps no state in the session, because inside another site's iframe the browser may send no
 * cookies: errors re-render the form with the posted values, and the receipt is a signed link.
 */
final class PublicBookingController
{
    public function __construct(
        private readonly BookingFormProjection $projection,
        private readonly SubmitBooking $submit,
        private readonly BookingIdentity $identity,
        private readonly LeadIngressHygiene $hygiene,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (($apex = WebsiteHosts::wwwApex($request->getHost())) !== null) {
            return redirect()->away('https://'.$apex.$request->getRequestUri(), 301);
        }

        $input = $request->only(['service', 'service_id', 'concern', 'source_page', 'source_host']);
        [$input['concern_choice'], $input['concern']] = $this->prefilledConcern(trim((string) ($input['concern'] ?? '')));

        return $this->render($request, $input, new MessageBag, '');
    }

    public function store(Request $request): Response|RedirectResponse
    {
        if (filled($request->input('company_website'))) {
            return $this->receiptRedirect($request, null);
        }

        $input = $this->postedInput($request);
        $store = $this->identity->store($request->input('identity_token'), $request->hasSession() ? $request->session() : null);
        $intent = (string) $request->input('book_intent', '');

        if ($intent !== '' && $intent !== 'submit') {
            return $this->handleIntent($request, $intent, $input, $store);
        }

        $render = LeadFormRenderStamp::read($request->input('form_rendered_at'));
        if ($render['status'] === LeadFormRenderStamp::Stale) {
            return $this->render($request, $input, new MessageBag(['form' => 'This form sat open too long. Check your details and send it again.']), $this->identity->token($store), 422);
        }

        $ingress = LeadIngressContext::fromRequest($request);
        $signals = $this->hygiene->signals($ingress);
        if (in_array('too_fast', $signals, true)) {
            return $this->render($request, $input, new MessageBag(['form' => 'Wait a few seconds, then send it again.']), $this->identity->token($store), 422);
        }

        try {
            $outcome = $this->submit->execute(
                $input,
                $store,
                $this->portalCustomer($request),
                $ingress,
                $this->hygiene->autoSpamState($signals) === LeadState::Spam ? LeadState::Spam : null,
                $signals,
            );
        } catch (ValidationException $exception) {
            return $this->render($request, $input, new MessageBag($exception->errors()), $this->identity->token($store), 422);
        }

        if ($outcome->created && $outcome->request?->lead !== null && $this->identity->required()) {
            AppointmentRequestConversion::arm($request, $outcome->request->lead);
        }

        return $this->receiptRedirect($request, $outcome->request);
    }

    public function availability(Request $request, BookingAvailability $availability): JsonResponse
    {
        $settings = BookingSettings::forShop();
        $service = BookingService::query()->offeredPublicly()->find((int) $request->query('service_id'));
        $location = $this->submit->locationFor($service, (string) $request->query('location', ''));
        $context = new BookingContext(
            $service,
            $service?->option((string) $request->query('service_option', ''))['label'] ?? null,
            max(1, min($settings['max_vehicles'], (int) $request->query('vehicles', 1))),
            $location,
            ['postal_code' => mb_substr(trim((string) $request->query('postal_code', '')), 0, 12)],
        );

        $result = $availability->forContext($context);
        $date = (string) $request->query('date', '');
        $open = array_column(array_filter($result['dates'], fn (array $d): bool => $d['available']), 'date');
        if (! in_array($date, $open, true)) {
            $date = $open[0] ?? '';
        }

        $result['selected_date'] = $date !== '' ? $date : null;
        $result['periods'] = $date !== '' && $result['mode'] === BookingMode::Request->value ? $availability->periodsFor($date) : [];
        $result['slots'] = $date !== '' && $result['mode'] === BookingMode::Instant->value ? $availability->slotsFor($date, $context) : [];

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function receipt(Request $request, string $uuid): Response
    {
        $bookingRequest = $uuid === 'received' ? null : AppointmentRequest::query()->where('uuid', $uuid)->first();
        $settings = BookingSettings::forShop();

        $website = app(PublishedWebsiteResolver::class)->forRequest($request);

        return $this->frameable($request, response()->view('booking.receipt', [
            'settings' => $settings,
            'bookingRequest' => $bookingRequest,
            'embed' => $request->boolean('embed'),
            'website' => $website,
            'seo' => $this->seo($website, $settings['title']),
        ]));
    }

    /** Changes when the booking scripts change, so a cached copy is never served after a release. */
    public static function assetVersion(): string
    {
        return substr(md5((string) @filemtime(public_path('js/ark/ark-booking-popup.js')).'|'.(string) @filemtime(public_path('js/ark/ark-booking-form.js'))), 0, 10);
    }

    public function popupScript(Request $request): Response
    {
        $settings = BookingSettings::forShop();
        $theme = CustomerThemes::current()->variables()['all'];
        $config = [
            'base' => $request->getSchemeAndHttpHost(),
            'label' => $settings['popup']['button_label'],
            'floating' => $settings['enabled'] && $settings['popup']['floating_button'],
            'enabled' => $settings['enabled'],
            'brand' => $theme['--public-brand'],
            'onBrand' => $theme['--public-on-brand'],
            'allowedHosts' => $settings['popup']['allowed_domains'],
        ];

        $script = (string) @file_get_contents(public_path('js/ark/ark-booking-popup.js'));

        return response('window.ArkBookingConfig='.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).";\n".$script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * A link's ?concern= becomes the closest of the shop's concern choices, keeping the customer's own
     * words as the description when they say more than the choice.
     *
     * @return array{0: string, 1: string}
     */
    private function prefilledConcern(string $concern): array
    {
        if ($concern === '' || strcasecmp($concern, PublicBookWizardConcerns::SOMETHING_ELSE) === 0) {
            return ['', ''];
        }

        $choices = BookingSettings::forShop()['concern_choices'];
        foreach ($choices as $choice) {
            if (strcasecmp($choice, $concern) === 0) {
                return [$choice, ''];
            }
        }

        $match = PublicBookWizardConcerns::matchCategory($concern);
        $choice = $match !== null && in_array($match, $choices, true) ? $match : '';

        return [$choice, mb_substr($concern, 0, 500)];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function handleIntent(Request $request, string $intent, array $input, Session $store): Response
    {
        $phone = (string) ($input['contact_phone'] ?? '');
        $email = (string) ($input['contact_email'] ?? '');
        $errors = new MessageBag;
        $status = null;

        $error = match ($intent) {
            'send_phone_code' => $this->identity->sendPhone($phone, $request->ip(), $request->userAgent()),
            'check_phone_code' => $this->identity->checkPhone($store, $phone, (string) $request->input('phone_code', '')),
            'send_email_code' => $this->identity->sendEmail($email, $request->ip(), $request->userAgent()),
            'check_email_code' => $this->identity->checkEmail($store, $email, (string) $request->input('email_code', '')),
            default => null,
        };

        if ($error !== null) {
            $errors->add(str_contains($intent, 'email') ? 'contact_email' : 'contact_phone', $error);
        } else {
            $status = match ($intent) {
                'send_phone_code', 'send_email_code' => 'Code sent. Enter it below.',
                'check_phone_code', 'check_email_code' => 'Verified. You can send your booking now.',
                default => null,
            };
        }

        $input['_intent'] = $intent;

        return $this->render($request, $input, $errors, $this->identity->token($store), 200, $status);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function render(Request $request, array $input, MessageBag $errors, string $identityToken, int $status = 200, ?string $notice = null): Response
    {
        $embed = $request->boolean('embed');
        $store = $this->identity->store($identityToken, $request->hasSession() ? $request->session() : null);
        $view = $this->projection->build($input, $this->portalCustomer($request, trustSession: $request->isMethod('GET')));
        $website = app(PublishedWebsiteResolver::class)->forRequest($request);

        $bag = new ViewErrorBag;
        $bag->put('default', $errors);

        return $this->frameable($request, response()->view('booking.page', [
            ...$view,
            'input' => $input,
            'errors' => $bag,
            'embed' => $embed,
            'identityToken' => $identityToken,
            'verifiedPhone' => $this->identity->verifiedPhone($store),
            'verifiedEmail' => $this->identity->verifiedEmail($store),
            'notice' => $notice,
            'renderStamp' => LeadFormRenderStamp::issue(),
            'submissionKey' => (string) ($input['submission_key'] ?? Str::random(40)),
            'website' => $website,
            'seo' => $this->seo($website, $view['settings']['title']),
        ], $status));
    }

    private function receiptRedirect(Request $request, ?AppointmentRequest $bookingRequest): RedirectResponse
    {
        $url = URL::temporarySignedRoute('public.book.received', now()->addHours(2), array_filter([
            'uuid' => $bookingRequest?->uuid ?? 'received',
            'embed' => $request->boolean('embed') ? 1 : null,
        ]));

        return redirect()->to($url, 303);
    }

    /**
     * @return array<string, mixed>
     */
    private function postedInput(Request $request): array
    {
        return $request->only([
            'service_id', 'service_option', 'location', 'address',
            'concern_choice', 'concern', 'unsafe_to_drive', 'needs_tow',
            'vehicles', 'vehicle_selection',
            'preferred_date', 'preferred_period', 'preferred_time',
            'contact_name', 'contact_phone', 'contact_email', 'contact_preference',
            'answers', 'submission_key', 'source_page', 'source_host', 'embed',
        ]);
    }

    /**
     * A signed-in portal customer is trusted only on first-party requests: a cross-site post
     * (no valid CSRF token) never books in their name.
     */
    private function portalCustomer(Request $request, bool $trustSession = false): ?Customer
    {
        if (! $request->hasSession()) {
            return null;
        }

        if (! $trustSession) {
            $token = (string) $request->input('_token', '');
            if ($token === '' || ! hash_equals((string) $request->session()->token(), $token)) {
                return null;
            }
        }

        $customer = Auth::guard('portal')->user();

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * Search and analytics tags when the shop publishes its website on this host.
     *
     * @return array{title: string, description: string, canonical: string, shop_name: string}|null
     */
    private function seo(?PublishedWebsite $website, string $title): ?array
    {
        if ($website === null) {
            return null;
        }

        return [
            'title' => $title,
            'description' => $website->seo('book')['description'],
            'canonical' => $website->canonicalUrl('/book'),
            'shop_name' => $website->shopName(),
        ];
    }

    private function frameable(Request $request, Response $response): Response
    {
        $ancestors = ["'self'"];
        if ($request->boolean('embed')) {
            foreach (BookingSettings::forShop()['popup']['allowed_domains'] as $domain) {
                $ancestors[] = 'https://'.$domain;
                $ancestors[] = 'https://www.'.$domain;
            }
        }

        $response->headers->set('Content-Security-Policy', 'frame-ancestors '.implode(' ', array_unique($ancestors)));
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
