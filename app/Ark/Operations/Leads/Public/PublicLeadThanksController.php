<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicLeadThanksController
{
    public function __invoke(Request $request, SeoEngine $seo): View
    {
        $shop = ShopSettings::current();
        $lead = $this->resolveLead($request);
        $thanks = LeadThanksProjection::forLead($lead);

        return view('public.success', [
            'shop' => $shop,
            'thanks' => $thanks,
            'seo' => $seo->forThanksPage()->toArray(),
            'title' => $shop->displayName().' — '.(($thanks->data['appointment_request'] ?? false)
                ? 'Appointment Requested'
                : 'Request received'),
        ]);
    }

    private function resolveLead(Request $request): ?Lead
    {
        $uuid = $request->session()->pull('website_lead_thanks_uuid');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return Lead::query()
            ->where('uuid', $uuid)
            ->where('state', '!=', LeadState::Spam)
            ->first();
    }
}
