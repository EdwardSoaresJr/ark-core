<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RepairOrderPartsTechQuoteImportController
{
    use RecordsRepairOrderEstimateMutation;
    public function preview(
        Request $request,
        RepairOrder $repairOrder,
        PartsTechActiveCartQuoteReader $reader,
        PartsTechCatalogLauncher $launcher,
        PartsTechCartPreparer $preparer,
    ): JsonResponse {
        $repairOrder->ensureOpenForEditing();

        abort_unless(
            $launcher->usesRemoteCartPreparation(auth()->user()),
            422,
            $launcher->usesPlatform()
                ? $launcher->blockedReason($repairOrder)
                : 'PartsTech quote import requires a PartsTech password on this profile or in shop settings.',
        );

        $poSynced = true;
        $poSyncWarning = null;

        $forceCartSwitch = $request->boolean('force_cart_switch');

        if ($preparer->canPrepare()) {
            try {
                $poSynced = $preparer->prepareOrReport($repairOrder, syncOnly: true, forceCartSwitch: $forceCartSwitch);

                if (! $poSynced) {
                    $poSyncWarning = $preparer->lastFailureMessage()
                        ?? 'PartsTech purchase order could not be synced.';
                }
            } catch (PartsTechShopSessionLockedException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'cart_locked' => true,
                    'blocking_cart_reference' => $exception->blockingCartReference,
                    'expected_cart_reference' => $launcher->partsTechCartReference($repairOrder),
                ], 423);
            }
        }

        try {
            $lines = $reader->linesForRepairOrder($repairOrder);
        } catch (PartsTechShopSessionLockedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'cart_locked' => true,
                'blocking_cart_reference' => $exception->blockingCartReference,
                'expected_cart_reference' => $launcher->partsTechCartReference($repairOrder),
            ], 423);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $repairOrder->load(['concerns.workGroups.lines', 'customer']);

        $settings = ShopSettings::current();
        $concerns = $repairOrder->concerns
            ->sortBy('position')
            ->values();

        $defaultConcern = $concerns->count() === 1 ? $concerns->first() : null;
        $defaultMatrix = $defaultConcern
            ? $defaultConcern->defaultPartsMatrix($settings)
            : $settings->defaultPartsMatrix();

        return response()->json([
            'repair_order_number' => $launcher->repairOrderNumber($repairOrder),
            'cart_reference' => $launcher->partsTechCartReference($repairOrder),
            'partstech_login' => $preparer->canPrepare() ? $preparer->actingLoginUsername() : null,
            'partstech_login_source' => $preparer->canPrepare() && $preparer->usesPersonalLogin() ? 'user' : 'shop',
            'default_parts_matrix_key' => $defaultMatrix['key'],
            'default_parts_matrix_name' => $defaultMatrix['name'],
            'default_repair_order_concern_id' => $defaultConcern?->id,
            'parts_matrices' => $settings->partsMatrices(),
            'po_synced' => $poSynced,
            'po_sync_warning' => $poSyncWarning,
            'warnings' => $preparer->canPrepare() ? $preparer->lastWarnings() : [],
            'lines' => array_map(
                fn (PartsTechQuoteLine $line): array => $line->toAssignmentPayload(),
                $lines,
            ),
            'concerns' => $concerns
                ->map(function (RepairOrderConcern $concern) use ($settings): array {
                    $matrix = $concern->defaultPartsMatrix($settings);

                    return [
                        'id' => $concern->id,
                        'summary' => $concern->summary,
                        'billing_posture' => $concern->billing_posture->value,
                        'default_parts_matrix_key' => $matrix['key'],
                        'default_parts_matrix_name' => $matrix['name'],
                        'work_groups' => $concern->workGroups
                            ->map(fn ($workGroup): array => [
                                'id' => $workGroup->id,
                                'title' => $workGroup->title,
                                'has_labor_anchor' => $workGroup->hasPartsAttachAnchor(),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->all(),
        ]);
    }

    public function store(
        Request $request,
        RepairOrder $repairOrder,
        PartsTechQuoteImporter $importer,
        PartsTechCatalogLauncher $launcher,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        abort_unless(
            $launcher->usesRemoteCartPreparation($request->user()),
            422,
            $launcher->usesPlatform()
                ? $launcher->blockedReason($repairOrder)
                : 'PartsTech quote import requires a PartsTech password on this profile or in shop settings.',
        );

        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.source_key' => ['required', 'string', 'max:128'],
            'assignments.*.repair_order_concern_id' => [
                'nullable',
                'integer',
                Rule::exists('repair_order_concerns', 'id')->where('repair_order_id', $repairOrder->id),
            ],
            'assignments.*.repair_order_work_group_id' => [
                'nullable',
                'integer',
                Rule::exists('repair_order_work_groups', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'repair_order_concern_id',
                        $repairOrder->concerns()->select('id'),
                    ),
                ),
            ],
            'assignments.*.part_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'assignments.*.pricing_matrix_key' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(collect(ShopSettings::current()->partsMatrices())->pluck('key')->all()),
            ],
        ]);

        $assignments = collect($data['assignments'])
            ->filter(fn (array $row): bool => filled($row['repair_order_concern_id'] ?? null))
            ->values()
            ->all();

        try {
            $result = $importer->importAssignments($repairOrder, $assignments, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->withFragment('estimate-lines')
                ->with('error', $exception->getMessage());
        }

        $concernList = implode(', ', $result['concerns']);
        $workGroupIds = $result['work_group_ids'] ?? [];
        $fragment = count($workGroupIds) === 1
            ? 'repair-action-'.$workGroupIds[0]
            : 'estimate-lines';

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment($fragment)
            ->with('status', sprintf(
                'Imported %d part line%s from PartsTech into %s.',
                $result['imported'],
                $result['imported'] === 1 ? '' : 's',
                $concernList !== '' ? $concernList : 'selected concerns',
            ));
    }
}
