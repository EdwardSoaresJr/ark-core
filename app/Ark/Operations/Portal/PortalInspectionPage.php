<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PortalInspectionPage
{
    public function __construct(
        private readonly InspectionReportProjection $report,
        private readonly CreateOrReuseInspectionAccessTokenAction $tokens,
    ) {}

    public function shouldRecordCustomerView(Request $request): bool
    {
        if (! PortalCustomerViewGate::shouldRecordCustomerView($request)) {
            return false;
        }

        $user = $request->user();

        if ($user === null) {
            return true;
        }

        return ! $user->can(ArkCapability::RepairOrdersManage->value);
    }

    public function normalizeMode(?string $view): string
    {
        return $view === InspectionReportProjection::MODE_DETAILED
            ? InspectionReportProjection::MODE_DETAILED
            : InspectionReportProjection::MODE_SIMPLE;
    }

    public function renderToken(
        InspectionAccessToken $accessToken,
        string $plainToken,
        bool $recordCustomerView,
        bool $staffPreview = false,
        string $mode = InspectionReportProjection::MODE_SIMPLE,
    ): View {
        if ($recordCustomerView) {
            $accessToken->forceFill(['last_viewed_at' => now()])->save();
            $accessToken = $accessToken->fresh();
        }

        $repairOrder = $accessToken->repairOrder()
            ->with(['customer', 'vehicle'])
            ->firstOrFail();

        $liveUrl = route('portal.inspections.show', ['token' => $plainToken]);

        $report = $this->report->for(
            repairOrder: $repairOrder,
            mode: $mode,
            photoUrlResolver: function (InspectionItemPhoto $photo) use ($plainToken): string {
                return route('portal.inspections.photos.show', [
                    'token' => $plainToken,
                    'photo' => $photo,
                ]);
            },
            liveReportUrl: $liveUrl,
        );

        return view('portal.inspection-report', [
            'repairOrder' => $repairOrder,
            'report' => $report,
            'mode' => $report['mode'],
            'access' => [
                'type' => 'token',
                'token' => $plainToken,
                'show_url' => $liveUrl,
                'print_url' => route('portal.inspections.print', ['token' => $plainToken, 'view' => $report['mode']]),
                'pdf_url' => route('portal.inspections.pdf', ['token' => $plainToken, 'view' => $report['mode']]),
                'simple_url' => route('portal.inspections.show', ['token' => $plainToken, 'view' => 'simple']),
                'detailed_url' => route('portal.inspections.show', ['token' => $plainToken, 'view' => 'detailed']),
            ],
            'staffPreview' => $staffPreview,
        ]);
    }

    /** @deprecated Use renderToken */
    public function render(
        InspectionAccessToken $accessToken,
        string $plainToken,
        bool $recordCustomerView,
        bool $staffPreview = false,
    ): View {
        return $this->renderToken($accessToken, $plainToken, $recordCustomerView, $staffPreview);
    }

    public function renderAuth(RepairOrder $repairOrder, string $mode): View
    {
        $vehicle = $repairOrder->vehicle;
        $authShow = route('portal.vehicles.inspections.show', [
            'vehicle' => $vehicle,
            'repairOrder' => $repairOrder,
        ]);

        $report = $this->report->for(
            repairOrder: $repairOrder,
            mode: $mode,
            photoUrlResolver: function (InspectionItemPhoto $photo) use ($vehicle, $repairOrder): string {
                return route('portal.vehicles.inspections.photos.show', [
                    'vehicle' => $vehicle,
                    'repairOrder' => $repairOrder,
                    'photo' => $photo,
                ]);
            },
            // Auth interactive page is not a share URL — QR/share minted only for print/PDF.
            liveReportUrl: null,
        );

        return view('portal.inspection-report', [
            'repairOrder' => $repairOrder,
            'report' => $report,
            'mode' => $report['mode'],
            'access' => [
                'type' => 'auth',
                'token' => null,
                'show_url' => $authShow,
                'print_url' => route('portal.vehicles.inspections.print', [
                    'vehicle' => $vehicle,
                    'repairOrder' => $repairOrder,
                    'view' => $report['mode'],
                ]),
                'pdf_url' => route('portal.vehicles.inspections.pdf', [
                    'vehicle' => $vehicle,
                    'repairOrder' => $repairOrder,
                    'view' => $report['mode'],
                ]),
                'simple_url' => route('portal.vehicles.inspections.show', [
                    'vehicle' => $vehicle,
                    'repairOrder' => $repairOrder,
                    'view' => 'simple',
                ]),
                'detailed_url' => route('portal.vehicles.inspections.show', [
                    'vehicle' => $vehicle,
                    'repairOrder' => $repairOrder,
                    'view' => 'detailed',
                ]),
            ],
            'staffPreview' => false,
        ]);
    }

    /**
     * Mint a durable share URL only when producing print/PDF QR (never for signed-in display).
     *
     * @return array{plain_token: string, url: string}
     */
    public function safeShare(RepairOrder $repairOrder): array
    {
        $share = $this->tokens->execute($repairOrder);

        return [
            'plain_token' => $share->plainToken,
            'url' => route('portal.inspections.show', ['token' => $share->plainToken]),
        ];
    }

    public function safeShareUrl(RepairOrder $repairOrder): string
    {
        return $this->safeShare($repairOrder)['url'];
    }

    /**
     * Print/PDF QR must never encode a short-lived staff-preview token.
     * Reuse a durable authorizing token; otherwise mint a durable share.
     *
     * @return array{plain_token: string, url: string}
     */
    public function durableShareForPrint(InspectionAccessToken $accessToken, string $plainToken): array
    {
        if ($accessToken->expires_at === null && $accessToken->isUsable()) {
            return [
                'plain_token' => $plainToken,
                'url' => route('portal.inspections.show', ['token' => $plainToken]),
            ];
        }

        $repairOrder = $accessToken->repairOrder()->firstOrFail();

        return $this->safeShare($repairOrder);
    }

    public function renderRepairPortal(RepairOrder $repairOrder, string $publicCode, string $mode): View
    {
        abort_unless(
            InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0,
            404,
        );

        $liveUrl = route('portal.repair.inspection.show', ['code' => $publicCode]);

        $report = $this->report->for(
            repairOrder: $repairOrder,
            mode: $mode,
            photoUrlResolver: function (InspectionItemPhoto $photo) use ($publicCode): string {
                return route('portal.repair.inspection.photos.show', [
                    'code' => $publicCode,
                    'photo' => $photo,
                ]);
            },
            liveReportUrl: $liveUrl,
        );

        return view('portal.inspection-report', [
            'repairOrder' => $repairOrder,
            'report' => $report,
            'mode' => $report['mode'],
            'access' => [
                'type' => 'repair_portal',
                'token' => null,
                'show_url' => $liveUrl,
                'print_url' => route('portal.repair.inspection.print', [
                    'code' => $publicCode,
                    'view' => $report['mode'],
                ]),
                'pdf_url' => route('portal.repair.inspection.pdf', [
                    'code' => $publicCode,
                    'view' => $report['mode'],
                ]),
                'simple_url' => route('portal.repair.inspection.show', [
                    'code' => $publicCode,
                    'view' => 'simple',
                ]),
                'detailed_url' => route('portal.repair.inspection.show', [
                    'code' => $publicCode,
                    'view' => 'detailed',
                ]),
            ],
            'staffPreview' => false,
        ]);
    }

    public function printHtml(RepairOrder $repairOrder, string $mode, string $liveReportUrl, ?string $plainToken = null): string
    {
        $photoResolver = null;
        if (filled($plainToken)) {
            $photoResolver = function (InspectionItemPhoto $photo) use ($plainToken): string {
                return route('portal.inspections.photos.show', [
                    'token' => $plainToken,
                    'photo' => $photo,
                ]);
            };
        }

        $report = $this->report->for(
            repairOrder: $repairOrder,
            mode: $mode,
            photoUrlResolver: $photoResolver,
            embedImageDataUris: true,
            liveReportUrl: $liveReportUrl,
        );

        $report['qr_data_uri'] = filled($liveReportUrl)
            ? CustomerReportQrCode::svgDataUri($liveReportUrl)
            : null;

        return view('portal.inspection-report-print', [
            'report' => $report,
            'mode' => $report['mode'],
        ])->render();
    }
}
