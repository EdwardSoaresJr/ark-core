<?php

namespace App\Ark\Operations\Approvals;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class StorePortalApprovalSignatureAction
{
    public function execute(int $approvalEventId, string $signatureDataUrl): string
    {
        $binary = $this->decodeSignature($signatureDataUrl);
        $path = sprintf('approval-signatures/%d.png', $approvalEventId);

        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function storePending(string $signatureDataUrl): string
    {
        $binary = $this->decodeSignature($signatureDataUrl);
        $path = 'approval-signatures/'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid()).'.png';

        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    private function decodeSignature(string $signatureDataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,(.+)$#', trim($signatureDataUrl), $matches)) {
            throw new InvalidArgumentException('Signature must be a PNG data URL.');
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || strlen($binary) < 32) {
            throw new InvalidArgumentException('Signature image is invalid.');
        }

        return $binary;
    }
}
