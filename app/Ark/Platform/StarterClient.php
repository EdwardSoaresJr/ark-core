<?php

namespace App\Ark\Platform;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Mail\TransactionalMailResult;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cloud-owned Starter transactional operations (estimate / final invoice).
 * Facts only — never HTML or arbitrary subjects.
 */
final class StarterClient
{
    public function isAvailable(): bool
    {
        return PlatformConnection::current()->isConnected()
            && filled(PlatformConnection::current()->credential());
    }

    /**
     * @return array{used: int, limit: int, period_key: string}|null
     */
    public function usageFromStatus(): ?array
    {
        $status = app(PlatformStatusClient::class)->fetch();
        $starter = is_array($status) ? ($status['starter'] ?? null) : null;
        if (! is_array($starter)) {
            return null;
        }

        return [
            'used' => (int) ($starter['used'] ?? 0),
            'limit' => (int) ($starter['limit'] ?? 20),
            'period_key' => (string) ($starter['period_key'] ?? ''),
        ];
    }

    public function sendEstimateReady(
        RepairOrder $repairOrder,
        string $recipientEmail,
        string $actionUrl,
        string $idempotencyKey,
    ): TransactionalMailResult {
        return $this->post(
            '/api/v1/services/starter/repair-orders/estimate-ready',
            $this->facts($repairOrder, $recipientEmail, $actionUrl, $idempotencyKey),
        );
    }

    public function sendFinalInvoiceReady(
        RepairOrder $repairOrder,
        string $recipientEmail,
        string $actionUrl,
        string $idempotencyKey,
    ): TransactionalMailResult {
        return $this->post(
            '/api/v1/services/starter/repair-orders/final-invoice-ready',
            $this->facts($repairOrder, $recipientEmail, $actionUrl, $idempotencyKey),
        );
    }

    /**
     * @return array<string, string>
     */
    private function facts(
        RepairOrder $repairOrder,
        string $recipientEmail,
        string $actionUrl,
        string $idempotencyKey,
    ): array {
        $repairOrder->loadMissing(['customer', 'vehicle']);
        $settings = ShopSettings::current();
        $customer = $repairOrder->customer;
        $vehicle = $repairOrder->vehicle;

        $vehicleDisplay = trim(implode(' ', array_filter([
            $vehicle?->year,
            $vehicle?->make,
            $vehicle?->model,
        ])));

        return [
            'repair_order_public_id' => $repairOrder->ensurePublicId(),
            'repair_order_number' => (string) $repairOrder->repair_order_id,
            'customer_display_name' => trim((string) ($customer?->name ?? $customer?->full_name ?? 'Customer')),
            'customer_email' => strtolower(trim($recipientEmail)),
            'vehicle_display' => $vehicleDisplay !== '' ? $vehicleDisplay : 'Vehicle',
            'action_url' => $actionUrl,
            'shop_display_name' => (string) ($settings->shop_name ?: config('app.name', 'ARK')),
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * @param  array<string, string>  $facts
     */
    private function post(string $path, array $facts): TransactionalMailResult
    {
        if (! $this->isAvailable()) {
            return TransactionalMailResult::notConfigured();
        }

        $cloud = PlatformConnection::current();
        $base = rtrim((string) $cloud->baseUrl(), '/');
        $credential = (string) $cloud->credential();
        $installationUuid = InstallationIdentity::uuid();
        $raw = json_encode($facts, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = Str::random(24);
        $signature = hash_hmac('sha256', implode("\n", [
            $timestamp,
            $nonce,
            'POST',
            $path,
            hash('sha256', $raw),
        ]), $credential);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Ark-Installation-Id' => $installationUuid,
                'X-Ark-Timestamp' => $timestamp,
                'X-Ark-Nonce' => $nonce,
                'X-Ark-Signature' => $signature,
            ])->withBody($raw, 'application/json')
                ->timeout(25)
                ->post($base.$path);
        } catch (\Throwable $e) {
            Log::warning('ark_cloud.starter_unavailable', ['error' => $e->getMessage()]);

            return TransactionalMailResult::providerError('ARK Platform Starter is temporarily unavailable.');
        }

        $json = $response->json();
        if ($response->successful() && is_array($json) && ($json['ok'] ?? false) === true) {
            return TransactionalMailResult::providerSent(
                is_string($json['correlation_id'] ?? null) ? $json['correlation_id'] : null,
                [
                    'usage' => $json['usage'] ?? null,
                    'grant_public_id' => $json['grant_public_id'] ?? null,
                ],
            );
        }

        $reason = is_array($json) ? (string) ($json['reason_code'] ?? 'rejected') : 'rejected';
        $message = match ($reason) {
            'allowance_exhausted' => 'ARK Platform Starter allowance is used up for this month. Core still works; included Cloud estimate and invoice delivery resume next month.',
            'grant_required' => 'Send the estimate through ARK Platform Starter before the final invoice.',
            'recipient_suppressed' => 'That email address cannot receive Cloud mail right now.',
            'attempt_limit_exceeded' => 'Too many delivery attempts for this repair order.',
            default => is_array($json) && is_string($json['message'] ?? null)
                ? $json['message']
                : 'ARK Platform could not deliver this message.',
        };

        return TransactionalMailResult::rejected($reason, $message);
    }
}
