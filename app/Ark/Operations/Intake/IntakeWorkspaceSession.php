<?php

namespace App\Ark\Operations\Intake;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Client workspace identity for parallel intake tabs ({@see config ark_workspace_tabs}).
 * The ws query param is ignored by intake business logic — it only scopes multitasking.
 */
final class IntakeWorkspaceSession
{
    public const QUERY_KEY = 'ws';

    public const LAUNCHER_ENTITY_ID = 'service';

    public static function generateId(): string
    {
        return substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    }

    public static function isValidId(?string $id): bool
    {
        if ($id === null || $id === '' || $id === self::LAUNCHER_ENTITY_ID) {
            return false;
        }

        return preg_match('/^[a-z0-9]{6,20}$/i', $id) === 1;
    }

    public static function idFromRequest(Request $request): ?string
    {
        $id = trim((string) $request->query(self::QUERY_KEY, ''));

        return self::isValidId($id) ? $id : null;
    }

    public static function idFromRequestOrInput(Request $request): ?string
    {
        $fromQuery = self::idFromRequest($request);

        if ($fromQuery !== null) {
            return $fromQuery;
        }

        $id = trim((string) $request->input(self::QUERY_KEY, ''));

        return self::isValidId($id) ? $id : null;
    }

    /**
     * @return array<string, int|string>
     */
    public static function paramsFromRequest(Request $request): array
    {
        return self::trailParams($request, fromInput: false);
    }

    /**
     * @return array<string, int|string>
     */
    public static function paramsFromRequestOrInput(Request $request): array
    {
        return self::trailParams($request, fromInput: true);
    }

    /**
     * @return array<string, int|string>
     */
    private static function trailParams(Request $request, bool $fromInput): array
    {
        $params = [];

        $workspaceId = $fromInput ? self::idFromRequestOrInput($request) : self::idFromRequest($request);

        if ($workspaceId !== null) {
            $params[self::QUERY_KEY] = $workspaceId;
        }

        $leadId = $fromInput ? $request->integer('lead_id') : $request->integer('lead_id');

        if ($leadId > 0) {
            $params['lead_id'] = $leadId;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function mergeParamsFromRequestOrInput(Request $request, array $params = []): array
    {
        return array_merge(self::paramsFromRequestOrInput($request), $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function routeFromRequestOrInput(Request $request, array $params = []): string
    {
        return self::route(self::mergeParamsFromRequestOrInput($request, $params));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function mergeParams(Request $request, array $params = []): array
    {
        return array_merge(self::paramsFromRequest($request), $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function route(array $params = []): string
    {
        return route('operations.intake.create', $params, false);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function routeFromRequest(Request $request, array $params = []): string
    {
        return self::route(self::mergeParams($request, $params));
    }

    public static function ensureOnRequest(Request $request): ?RedirectResponse
    {
        if (self::idFromRequest($request) !== null) {
            return null;
        }

        $query = $request->query();
        $query[self::QUERY_KEY] = self::generateId();

        return redirect()->to(self::route($query));
    }
}
