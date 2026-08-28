<?php

namespace App\Ark\Tech;

use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionMeasurementSlots;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Voice\Lab\VoiceLabTranscriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TechVoiceProposalService
{
    public function __construct(
        private readonly VoiceLabTranscriber $transcriber,
        private readonly TechSchemaSpeechParser $parser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function proposeFromAudioOrTranscript(?string $wavBytes, ?string $transcriptHint, int $itemId, int $repairOrderId, int $userId): array
    {
        $transcript = trim((string) $transcriptHint);
        if ($transcript === '') {
            if ($wavBytes === null || strlen($wavBytes) < 44) {
                throw new RuntimeException('Audio or transcript is required.');
            }
            try {
                $transcript = $this->transcriber->transcribeWav($wavBytes);
            } catch (Throwable) {
                throw new RuntimeException('Speech-to-text unavailable. Enter measurements manually.');
            }
        }

        $item = InspectionItem::query()->find($itemId);
        $templateItem = $item?->inspection_template_item_id
            ? InspectionTemplateItem::query()->find($item->inspection_template_item_id)
            : null;
        $slots = array_values(array_filter(
            InspectionMeasurementSlots::fromTemplateItem($templateItem),
            fn (array $slot): bool => ($slot['type'] ?? 'number') === 'number',
        ));

        $parsed = $this->parser->parse($transcript, $slots);
        $measurements = $parsed['measurements'];

        $id = (string) Str::uuid();

        $payload = [
            'id' => $id,
            'user_id' => $userId,
            'repair_order_id' => $repairOrderId,
            'inspection_item_id' => $itemId,
            'transcript' => $transcript,
            'measurements' => $measurements,
            'rotor_condition' => $parsed['rotor_condition'],
            'finding' => $parsed['finding'],
            'condition' => $parsed['condition'],
            'written' => false,
        ];

        Cache::put($this->cacheKey($id), $payload, now()->addMinutes(20));

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(string $id): ?array
    {
        $payload = Cache::get($this->cacheKey($id));

        return is_array($payload) ? $payload : null;
    }

    public function markWritten(string $id): void
    {
        $payload = $this->pull($id);
        if ($payload === null) {
            return;
        }
        $payload['written'] = true;
        Cache::put($this->cacheKey($id), $payload, now()->addMinutes(20));
    }

    private function cacheKey(string $id): string
    {
        return 'tech.voice.proposal.'.$id;
    }
}
