<?php

namespace App\Ark\Communications\Provisioning;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EndpointProvisionController
{
    public function __invoke(
        string $filename,
        ServeEndpointProvisionArtifactAction $serve,
        EndpointProvisionObservationLogger $observations,
    ): Response {
        $started = microtime(true);
        $parsed = EndpointProvisionFilename::parse($filename);

        if ($parsed === null) {
            abort(404);
        }

        $normalizedMac = $parsed->normalizedMac ?? '000000000000';

        if ($parsed->normalizedMac !== null && $parsed->normalizedMac !== '000000000000') {
            app(\App\Ark\Operations\Communications\DiscoverCommunicationDeviceFromProvisionProbeAction::class)
                ->execute($parsed->normalizedMac);
        }

        try {
            $result = $serve->execute($parsed);
        } catch (EndpointProvisionNotFoundException) {
            $observations->logNotFound($normalizedMac, (microtime(true) - $started) * 1000, $parsed->artifact);
            abort(404);
        } catch (EndpointProvisionGateException $exception) {
            $observations->logGateFailure(
                $normalizedMac,
                $exception->failure,
                (microtime(true) - $started) * 1000,
                $parsed->artifact,
            );
            abort($exception->failure->httpStatus(), $exception->failure->message());
        } catch (EndpointProvisionMisconfiguredException $exception) {
            return $this->misconfiguredResponse($normalizedMac, $exception, $started, $observations, $parsed->artifact);
        } catch (\Throwable $exception) {
            return $this->unexpectedFailureResponse($normalizedMac, $exception, $started);
        }

        if ($result->projectionResult instanceof RegenerateEndpointConfigurationResult) {
            $observations->logSuccess($normalizedMac, $result->projectionResult, (microtime(true) - $started) * 1000, $parsed->artifact);
        } else {
            $observations->logArtifact($normalizedMac, $parsed->artifact, (microtime(true) - $started) * 1000);
        }

        return response(
            $result->body,
            200,
            [
                'Content-Type' => $this->contentTypeFor($parsed->artifact),
                'Cache-Control' => 'no-store',
            ],
        );
    }

    public function config(
        string $mac,
        ServeEndpointProvisionArtifactAction $serve,
        EndpointProvisionObservationLogger $observations,
    ): Response {
        $started = microtime(true);
        $parsed = EndpointProvisionFilename::fromConfigPath($mac);
        $normalizedMac = $parsed->normalizedMac ?? $mac;

        if ($parsed->normalizedMac !== null && $parsed->normalizedMac !== '000000000000') {
            app(\App\Ark\Operations\Communications\DiscoverCommunicationDeviceFromProvisionProbeAction::class)
                ->execute($parsed->normalizedMac);
        }

        try {
            $result = $serve->execute($parsed);
        } catch (EndpointProvisionNotFoundException) {
            $observations->logNotFound($normalizedMac, (microtime(true) - $started) * 1000, $parsed->artifact);
            abort(404);
        } catch (EndpointProvisionGateException $exception) {
            $observations->logGateFailure(
                $normalizedMac,
                $exception->failure,
                (microtime(true) - $started) * 1000,
                $parsed->artifact,
            );
            abort($exception->failure->httpStatus(), $exception->failure->message());
        } catch (EndpointProvisionMisconfiguredException $exception) {
            return $this->misconfiguredResponse($normalizedMac, $exception, $started, $observations, $parsed->artifact);
        } catch (\Throwable $exception) {
            return $this->unexpectedFailureResponse($normalizedMac, $exception, $started);
        }

        $observations->logSuccess(
            $normalizedMac,
            $result->projectionResult ?? throw new \LogicException('Device config must include projection result.'),
            (microtime(true) - $started) * 1000,
            $parsed->artifact,
        );

        return response(
            $result->body,
            200,
            [
                'Content-Type' => 'text/xml; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    private function contentTypeFor(EndpointProvisionArtifact $artifact): string
    {
        return match ($artifact) {
            EndpointProvisionArtifact::Directory => 'text/xml; charset=UTF-8',
            default => 'text/plain; charset=UTF-8',
        };
    }

    private function misconfiguredResponse(
        string $normalizedMac,
        EndpointProvisionMisconfiguredException $exception,
        float $started,
        EndpointProvisionObservationLogger $observations,
        EndpointProvisionArtifact $artifact,
    ): Response {
        Log::warning('endpoint.provision.misconfigured', [
            'mac' => $normalizedMac,
            'artifact' => $artifact->value,
            'reason' => $exception->getMessage(),
            'duration_ms' => round((microtime(true) - $started) * 1000, 1),
        ]);

        $observations->logGateFailure(
            $normalizedMac,
            EndpointProvisionGateFailure::Misconfigured,
            (microtime(true) - $started) * 1000,
            $artifact,
        );

        return response(
            $exception->getMessage(),
            503,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    private function unexpectedFailureResponse(
        string $normalizedMac,
        Throwable $exception,
        float $started,
    ): Response {
        Log::error('endpoint.provision.error', [
            'mac' => $normalizedMac,
            'reason' => $exception->getMessage(),
            'duration_ms' => round((microtime(true) - $started) * 1000, 1),
        ]);

        return response(
            'Provisioning failed.',
            503,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
