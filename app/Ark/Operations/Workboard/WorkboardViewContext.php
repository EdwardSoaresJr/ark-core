<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class WorkboardViewContext
{
    /**
     * @param  Collection<int, User>  $technicianOptions
     */
    public function __construct(
        public readonly string $lens,
        public readonly User $filterUser,
        public readonly bool $canToggleLens,
        public readonly ?User $previewTechnician,
        public readonly Collection $technicianOptions,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var User $user */
        $user = $request->user();
        $naturalLens = WorkboardLens::forUser($user);

        if (! WorkboardLens::canToggleLens($user)) {
            return new self(
                lens: $naturalLens,
                filterUser: $user,
                canToggleLens: false,
                previewTechnician: null,
                technicianOptions: collect(),
            );
        }

        $lens = $request->query('lens') === WorkboardLens::TECHNICIAN
            ? WorkboardLens::TECHNICIAN
            : WorkboardLens::ADVISOR;

        $technicianOptions = WorkboardLens::activeTechnicians();
        $previewTechnician = null;

        if ($lens === WorkboardLens::TECHNICIAN) {
            $technicianId = $request->integer('technician');

            if ($technicianId > 0) {
                $previewTechnician = $technicianOptions->firstWhere('id', $technicianId);
            }

            $previewTechnician ??= $technicianOptions->first();
        }

        $filterUser = $lens === WorkboardLens::TECHNICIAN && $previewTechnician !== null
            ? $previewTechnician
            : $user;

        return new self(
            lens: $lens,
            filterUser: $filterUser,
            canToggleLens: true,
            previewTechnician: $previewTechnician,
            technicianOptions: $technicianOptions,
        );
    }

    public function isTechnicianLens(): bool
    {
        return $this->lens === WorkboardLens::TECHNICIAN;
    }

    public function isPreview(): bool
    {
        return $this->canToggleLens && $this->isTechnicianLens();
    }

    public function urlForLens(string $lens, ?int $technicianId = null): string
    {
        if ($lens !== WorkboardLens::TECHNICIAN) {
            return route('operations.workboard');
        }

        $params = ['lens' => WorkboardLens::TECHNICIAN];

        $resolvedTechnicianId = $technicianId
            ?? $this->previewTechnician?->id
            ?? $this->technicianOptions->first()?->id;

        if ($resolvedTechnicianId !== null) {
            $params['technician'] = $resolvedTechnicianId;
        }

        return route('operations.workboard', $params);
    }
}
