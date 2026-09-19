<?php

namespace App\Ark\Vehicles\Providers;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Vehicles\CanonicalVehicleIdentity;
use App\Ark\Vehicles\PartsTechDecodeException;
use App\Ark\Vehicles\PlateDecoder;
use App\Ark\Vehicles\RawVehicleIdentity;
use App\Ark\Vehicles\VehicleNormalizer;
use App\Ark\Vehicles\VehicleText;
use App\Ark\Vehicles\VinDecoder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PartsTechProvider implements VinDecoder, PlateDecoder
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly VehicleNormalizer $normalizer,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function decode(string $vin): ?CanonicalVehicleIdentity
    {
        try {
            return $this->catalogDecode('catalog/vin/'.rawurlencode($vin), $vin);
        } catch (Throwable) {
            return null;
        }
    }

    public function decodePlate(string $plate, string $state): ?CanonicalVehicleIdentity
    {
        $plate = strtoupper(preg_replace('/\s+/', '', trim($plate)) ?? '');
        $state = strtoupper(trim($state));

        if ($plate === '') {
            throw new PartsTechDecodeException('Enter a license plate to decode.');
        }

        if (strlen($state) !== 2) {
            throw new PartsTechDecodeException('Enter the 2-letter plate state, like CO.');
        }

        if (! $this->configured()) {
            throw new PartsTechDecodeException('PartsTech is not set up for plate decode. Add the username and API key under Settings → PartsTech.');
        }

        try {
            $identity = $this->catalogDecode('catalog/plate/'.rawurlencode($state).'/'.rawurlencode($plate));
        } catch (PartsTechDecodeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PartsTechDecodeException('PartsTech plate decode is unavailable right now.');
        }

        if ($identity === null || ! $identity->isUsable()) {
            throw new PartsTechDecodeException('PartsTech could not identify that plate.');
        }

        return $identity;
    }

    private function configured(): bool
    {
        return filled($this->credentials->partsTechUsername()) && filled($this->credentials->partsTechApiKey());
    }

    private function catalogDecode(string $path, ?string $fallbackVin = null): ?CanonicalVehicleIdentity
    {
        $token = $this->token();

        if ($token === null) {
            throw new PartsTechDecodeException('PartsTech rejected the API key. Check Settings → PartsTech.');
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($token)
            ->get($this->apiBase().'/'.$path);

        if ($response->status() === 400) {
            $message = $this->errorMessage($response);

            throw new PartsTechDecodeException($message !== '' ? $message : 'PartsTech could not decode that vehicle.');
        }

        if (! $response->ok()) {
            return null;
        }

        return $this->mapPayload($response->json(), $fallbackVin);
    }

    private function token(): ?string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $username = $this->credentials->partsTechUsername();
        $apiKey = $this->credentials->partsTechApiKey();

        if (! filled($username) || ! filled($apiKey)) {
            return null;
        }

        $partnerId = trim((string) config('services.partstech.partner_id', ''));
        $partnerKey = trim((string) config('services.partstech.partner_key', ''));

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post($this->apiBase().'/oauth/access', [
                    'accessType' => 'user',
                    'credentials' => [
                        'user' => [
                            'id' => $username,
                            'key' => $apiKey,
                        ],
                        'partner' => [
                            'id' => $partnerId !== '' ? $partnerId : $username,
                            'key' => $partnerKey !== '' ? $partnerKey : $apiKey,
                        ],
                    ],
                ]);
        } catch (Throwable) {
            return null;
        }

        $token = $response->ok() ? $response->json('accessToken') : null;
        $this->accessToken = is_string($token) && $token !== '' ? $token : null;

        return $this->accessToken;
    }

    private function apiBase(): string
    {
        $configured = trim((string) config('services.partstech.api_base_url', ''));

        return rtrim($configured !== '' ? $configured : 'https://api.partstech.com', '/');
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? trim($message) : '';
    }

    private function mapPayload(mixed $payload, ?string $fallbackVin = null): ?CanonicalVehicleIdentity
    {
        $row = $this->firstRow($payload);

        if ($row === null) {
            return null;
        }

        $decode = $row['vinDecode'] ?? $row['vehicle'] ?? $row;
        $decode = is_array($decode) ? $decode : [];
        $vin = filled($fallbackVin)
            ? $fallbackVin
            : (new \App\Ark\Vehicles\VinNormalizer)->coerceInput($row['vin'] ?? $this->field($decode, ['vin']));

        return $this->normalizer->normalize(new RawVehicleIdentity(
            vin: $vin,
            year: $this->field($decode, ['year', 'modelYear', 'model_year']),
            make: $this->field($decode, ['make']),
            model: $this->field($decode, ['model']),
            trim: $this->field($decode, ['submodel', 'subModel', 'trim']),
            engine: $this->field($decode, ['engine', 'engineName']),
            engineCode: $this->field($decode, ['engineCode', 'engine_code']),
            displacementLiters: $this->field($decode, ['displacementLiters', 'displacement', 'displacementL']),
            fuelType: $this->field($decode, ['fuelType', 'fuel_type', 'fuel']),
            aspiration: $this->field($decode, ['aspiration']),
            drivetrain: $this->field($decode, ['driveType', 'drive', 'drivetrain']),
            transmission: $this->field($decode, ['transmission', 'transmissionType']),
            bodyStyle: $this->field($decode, ['body', 'bodyStyle', 'body_style', 'bodyClass']),
            manufacturer: $this->field($decode, ['manufacturer']),
            source: 'partstech',
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstRow(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        if (array_is_list($payload)) {
            $first = $payload[0] ?? null;

            return is_array($first) ? $first : null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $decode
     * @param  list<string>  $keys
     */
    private function field(array $decode, array $keys): ?string
    {
        $lower = [];

        foreach ($decode as $key => $value) {
            $lower[strtolower((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $value = $lower[strtolower($key)] ?? null;

            if (is_scalar($value)) {
                $cleaned = VehicleText::clean((string) $value);

                if ($cleaned !== null) {
                    return $cleaned;
                }
            }
        }

        return null;
    }
}
