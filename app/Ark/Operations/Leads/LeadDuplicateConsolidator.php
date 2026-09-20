<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Collection;

final class LeadDuplicateConsolidator
{
    public function __construct(
        private readonly LeadConcernComparator $concerns,
    ) {}

    /**
     * Close duplicate open leads on the same phone that belong to the same intake thread.
     *
     * @return list<array{duplicate_id: int, canonical_id: int, contact_phone: string}>
     */
    public function consolidateAround(Lead $canonical, bool $dryRun = false): array
    {
        if ($canonical->contact_phone === null || ! $canonical->isOpen()) {
            return [];
        }

        return $this->consolidatePhoneGroup(
            $this->openLeadsForPhone($canonical->contact_phone),
            $dryRun,
            $canonical,
        );
    }

    /**
     * @return list<array{duplicate_id: int, canonical_id: int, contact_phone: string}>
     */
    public function consolidateOpenDuplicates(?string $phone = null, bool $dryRun = false): array
    {
        $query = Lead::query()
            ->open()
            ->whereNotNull('contact_phone');

        if ($phone !== null) {
            $query->where('contact_phone', PhoneNumber::normalize($phone));
        }

        $closed = [];

        $query->get()
            ->groupBy('contact_phone')
            ->each(function (Collection $leads) use (&$closed, $dryRun): void {
                $closed = [...$closed, ...$this->consolidatePhoneGroup($leads, $dryRun)];
            });

        return $closed;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return list<array{duplicate_id: int, canonical_id: int, contact_phone: string}>
     */
    private function consolidatePhoneGroup(Collection $leads, bool $dryRun, ?Lead $forcedCanonical = null): array
    {
        if ($leads->count() < 2) {
            return [];
        }

        $closed = [];

        foreach ($this->threadClusters($leads) as $cluster) {
            if ($cluster->count() < 2) {
                continue;
            }

            $canonical = $forcedCanonical instanceof Lead && $cluster->contains('id', $forcedCanonical->id)
                ? $forcedCanonical
                : $this->selectCanonicalLead($cluster);

            foreach ($cluster as $lead) {
                if ($lead->id === $canonical->id) {
                    continue;
                }

                if (! $dryRun) {
                    $lead->state = LeadState::Lost;
                    $lead->lost_at = now();
                    $lead->lost_reason = "Duplicate intake — consolidated into lead #{$canonical->id}";
                    $lead->save();
                }

                $closed[] = [
                    'duplicate_id' => $lead->id,
                    'canonical_id' => $canonical->id,
                    'contact_phone' => (string) $lead->contact_phone,
                ];
            }
        }

        return $closed;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return list<Collection<int, Lead>>
     */
    private function threadClusters(Collection $leads): array
    {
        $sorted = $leads->sortBy('created_at')->values();
        $clusters = [];

        foreach ($sorted as $lead) {
            $placed = false;

            foreach ($clusters as &$cluster) {
                /** @var Lead $representative */
                $representative = $cluster->first();

                if ($this->concerns->belongsToSameThread($representative->concern, $lead->concern)) {
                    $cluster->push($lead);
                    $placed = true;
                    break;
                }
            }

            unset($cluster);

            if (! $placed) {
                $clusters[] = collect([$lead]);
            }
        }

        return $clusters;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function selectCanonicalLead(Collection $leads): Lead
    {
        return $leads
            ->sort(function (Lead $a, Lead $b): int {
                $scoreA = $this->canonicalScore($a);
                $scoreB = $this->canonicalScore($b);

                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }

                return $a->created_at <=> $b->created_at;
            })
            ->first();
    }

    private function canonicalScore(Lead $lead): int
    {
        return ($lead->contact_name ? 10 : 0)
            + ($lead->roughVehicleLabel() ? 5 : 0)
            + ($lead->source === LeadSource::Website ? 3 : 0);
    }

    /**
     * @return Collection<int, Lead>
     */
    private function openLeadsForPhone(string $phone): Collection
    {
        return Lead::query()
            ->open()
            ->where('contact_phone', $phone)
            ->orderBy('created_at')
            ->get();
    }
}
