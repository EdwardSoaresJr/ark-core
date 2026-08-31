<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Dragon\Agent\DragonAgentMemory;
use App\Ark\Operations\Diagnostics\OperationalClockProjection;
use App\Ark\Operations\EstimatePricing\LaborPoliciesMatrixProjection;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolverPreview;
use App\Ark\Operations\Inspections\InspectionTemplateCatalog;
use App\Ark\Operations\Messaging\MessagingHealth;
use App\Ark\Operations\Messaging\Messenger\MessengerHealth;
use App\Ark\Operations\Printing\QzPrintingSettingsReference;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Staff\StaffMemberController;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Telephony\TelephonyHealth;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopSettingsPageController
{
    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->query('section') === 'communications'
            && $request->query('communications-tab') === 'ark-voice') {
            return redirect()->route('operations.shop.communications');
        }

        if ($request->query('section') === 'public-surface') {
            return redirect()->route('website.manage');
        }

        $initialSection = $request->query('section');
        $allowedSections = ['general', 'financial', 'payments', 'communications', 'overhead', 'excellence', 'estimates', 'workflow', 'operations', 'printing', 'staff', 'dragon-memory', 'runtime-health'];

        if (! in_array($initialSection, $allowedSections, true)) {
            $initialSection = $request->old('_member') !== null || $request->old('roles') !== null
                ? 'staff'
                : null;
        }

        return view('operations.settings.shop', [
            'settings' => ShopSettings::current(),
            'staff' => StaffMemberController::list(),
            'staffRoles' => ArkRole::staffAssignable(),
            'initialSection' => $initialSection,
            'qzPrintingReference' => QzPrintingSettingsReference::forSettingsPage(),
            'telephonyHealth' => TelephonyHealth::forCurrentShop(),
            'messagingHealth' => app(MessagingHealth::class),
            'messengerHealth' => MessengerHealth::forCurrentShop(),
            'shopIntegrations' => ShopIntegrationCredentials::forCurrentShop(),
            'telephonyEndpoints' => TelephonyEndpoint::query()->with('user')->orderBy('position')->orderBy('id')->get(),
            'telephonyEndpointTypes' => TelephonyEndpointType::cases(),
            'telephonyExtensions' => TelephonyExtension::query()->with('user')->orderBy('extension')->get(),
            'telephonyExtensionDeviceTypes' => TelephonyExtensionDeviceType::cases(),
            'excellenceTargets' => ShopExcellenceTargets::current(),
            'excellenceTargetReview' => ShopExcellenceTargets::lastTargetReview(),
            'statusCatalogFormData' => app(RepairOrderStatusCatalog::class)->settingsFormData(),
            'inspectionTemplates' => InspectionTemplateCatalog::settingsFormData(),
            'workTemplates' => \App\Ark\Operations\WorkTemplates\WorkTemplate::query()
                ->with('lines')
                ->orderByRaw('retired_at is not null')
                ->orderBy('position')
                ->orderBy('title')
                ->get(),
            'operationalClock' => OperationalClockProjection::resolve(),
            'laborPoliciesMatrix' => app(LaborPoliciesMatrixProjection::class)->build(),
            'laborPolicyPreview' => app(LaborPolicyResolverPreview::class)->for(
                $request->query('lp_posture'),
                $request->query('lp_class'),
            ),
            'scheduleBays' => Workstation::query()
                ->where('accepts_scheduled_work', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'dragonMemories' => DragonAgentMemory::query()
                ->with(['workstation', 'user'])
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
        ]);
    }
}
