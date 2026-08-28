<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * HTTP transport for Coolify API. Endpoint paths live only here.
 */
final class HttpCoolifyClient implements CoolifyClient
{
    private const ENDPOINT_TEAMS = '/api/v1/teams';

    private const ENDPOINT_SERVERS = '/api/v1/servers';

    private const ENDPOINT_PROJECTS = '/api/v1/projects';

    private const ENDPOINT_APPLICATIONS = '/api/v1/applications';

    private const ENDPOINT_DEPLOY = '/api/v1/deploy';

    private const ENDPOINT_DEPLOYMENT = '/api/v1/deployments/%s';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 5,
    ) {
        if ($this->baseUrl === '' || $this->token === '') {
            throw new CoolifyException('Coolify base URL and API token are required.', operation: 'config');
        }
    }

    public function authenticate(): CoolifyAuthenticationResult
    {
        $payload = $this->getJson(self::ENDPOINT_TEAMS, 'authenticate', retryTransport: true);
        $count = is_array($payload) ? count($payload) : 0;

        return new CoolifyAuthenticationResult(authenticated: true, teamCount: $count);
    }

    public function listServers(): Collection
    {
        $payload = $this->getJson(self::ENDPOINT_SERVERS, 'listServers', retryTransport: true);

        return collect(is_array($payload) ? $payload : [])
            ->map(function (mixed $row): ?CoolifyServer {
                if (! is_array($row)) {
                    return null;
                }
                $uuid = (string) ($row['uuid'] ?? '');
                $name = (string) ($row['name'] ?? $uuid);
                if ($uuid === '') {
                    return null;
                }

                return new CoolifyServer($uuid, $name);
            })
            ->filter()
            ->values();
    }

    public function listProjects(): Collection
    {
        $payload = $this->getJson(self::ENDPOINT_PROJECTS, 'listProjects', retryTransport: true);

        return collect(is_array($payload) ? $payload : [])
            ->map(function (mixed $row): ?CoolifyProject {
                if (! is_array($row)) {
                    return null;
                }
                $uuid = (string) ($row['uuid'] ?? '');
                $name = (string) ($row['name'] ?? $uuid);
                if ($uuid === '') {
                    return null;
                }

                return new CoolifyProject($uuid, $name);
            })
            ->filter()
            ->values();
    }

    public function listApplications(): Collection
    {
        $payload = $this->getJson(self::ENDPOINT_APPLICATIONS, 'listApplications', retryTransport: true);

        return collect(is_array($payload) ? $payload : [])
            ->map(function (mixed $row): ?CoolifyApplication {
                if (! is_array($row)) {
                    return null;
                }
                $uuid = (string) ($row['uuid'] ?? '');
                $name = (string) ($row['name'] ?? $uuid);
                if ($uuid === '') {
                    return null;
                }
                $serverUuid = isset($row['destination']['server']['uuid'])
                    ? (string) $row['destination']['server']['uuid']
                    : (isset($row['server_id']) ? (string) $row['server_id'] : null);

                return new CoolifyApplication($uuid, $name, $serverUuid !== '' ? $serverUuid : null);
            })
            ->filter()
            ->values();
    }

    public function triggerDeployment(CoolifyDeploymentCommand $command): CoolifyDeploymentResult
    {
        // GET is Coolify's canonical deploy trigger; do not auto-retry (not idempotent).
        $response = $this->http()
            ->get($this->baseUrl.self::ENDPOINT_DEPLOY, [
                'uuid' => $command->applicationUuid,
            ]);

        if (! $response->successful()) {
            throw new CoolifyException(
                'Coolify deploy failed: HTTP '.$response->status().' '.CoolifyMessageSanitizer::sanitize($response->body()),
                httpStatus: $response->status(),
                operation: 'triggerDeployment',
                retryable: $response->serverError(),
            );
        }

        $payload = $response->json();
        $reference = '';
        if (is_array($payload)) {
            $reference = (string) ($payload['deployment_uuid']
                ?? $payload['uuid']
                ?? (is_array($payload['deployments'][0] ?? null) ? ($payload['deployments'][0]['deployment_uuid'] ?? $payload['deployments'][0]['uuid'] ?? '') : '')
                ?? '');
        }

        if ($reference === '') {
            // Some Coolify versions return a message without uuid; use application uuid as observation key.
            $reference = 'app:'.$command->applicationUuid;
        }

        return new CoolifyDeploymentResult($reference, status: 'queued');
    }

    public function deploymentStatus(string $deploymentReference): CoolifyDeploymentStatus
    {
        if (str_starts_with($deploymentReference, 'app:')) {
            $appUuid = substr($deploymentReference, 4);
            $payload = $this->getJson(
                '/api/v1/deployments/applications/'.$appUuid,
                'deploymentStatus',
                retryTransport: true,
            );
            $latest = is_array($payload) ? ($payload[0] ?? $payload) : [];
            $status = is_array($latest) ? (string) ($latest['status'] ?? 'unknown') : 'unknown';
            $uuid = is_array($latest) ? (string) ($latest['deployment_uuid'] ?? $latest['uuid'] ?? $deploymentReference) : $deploymentReference;

            return new CoolifyDeploymentStatus($uuid, $status);
        }

        $payload = $this->getJson(
            sprintf(self::ENDPOINT_DEPLOYMENT, $deploymentReference),
            'deploymentStatus',
            retryTransport: true,
        );
        $status = is_array($payload) ? (string) ($payload['status'] ?? 'unknown') : 'unknown';

        return new CoolifyDeploymentStatus($deploymentReference, $status);
    }

    /**
     * @return array<mixed>|null
     */
    private function getJson(string $path, string $operation, bool $retryTransport): ?array
    {
        try {
            $pending = $this->http();
            if ($retryTransport) {
                $pending = $pending->retry(2, 100, function ($exception) {
                    return $exception instanceof ConnectionException;
                });
            }

            $response = $pending->get($this->baseUrl.$path);
        } catch (ConnectionException $e) {
            throw new CoolifyException(
                'Coolify connection failed during '.$operation.': '.CoolifyMessageSanitizer::sanitize($e->getMessage()),
                operation: $operation,
                retryable: true,
            );
        } catch (RequestException $e) {
            throw new CoolifyException(
                'Coolify '.$operation.' failed: '.CoolifyMessageSanitizer::sanitize($e->getMessage()),
                httpStatus: $e->response?->status(),
                operation: $operation,
                retryable: (bool) $e->response?->serverError(),
            );
        }

        if (! $response->successful()) {
            throw new CoolifyException(
                'Coolify '.$operation.' failed: HTTP '.$response->status().' '.CoolifyMessageSanitizer::sanitize($response->body()),
                httpStatus: $response->status(),
                operation: $operation,
                retryable: $response->serverError(),
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withOptions([
                // Prevent accidental Authorization logging via debug dumps.
                'http_errors' => false,
            ]);
    }
}
