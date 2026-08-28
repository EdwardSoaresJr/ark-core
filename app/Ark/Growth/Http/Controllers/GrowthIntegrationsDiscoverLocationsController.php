<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Integrations\Google\GoogleBusinessProfileLocationLister;
use App\Ark\Growth\Integrations\Google\GoogleGrowthApiException;
use App\Ark\Growth\Integrations\Google\GoogleServiceAccountCredentials;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Http\Requests\Growth\DiscoverGrowthIntegrationsLocationsRequest;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class GrowthIntegrationsDiscoverLocationsController
{
    public function __invoke(
        DiscoverGrowthIntegrationsLocationsRequest $request,
        GoogleBusinessProfileLocationLister $lister,
    ): JsonResponse {
        try {
            $inlineCredentials = GoogleServiceAccountCredentials::tryParse(
                (string) $request->input('growth_google_service_account_json', ''),
            );

            $credentials = $inlineCredentials
                ?? GrowthIntegrationSettings::current()->googleServiceAccountCredentials();

            if ($credentials === null) {
                return response()->json([
                    'message' => 'Paste the Google service account JSON, then click Discover locations. Or save the JSON first.',
                ], 422);
            }

            return response()->json([
                'locations' => $lister->listLocations($credentials),
                'credentials_source' => $inlineCredentials !== null ? 'request' : GrowthIntegrationSettings::current()->googleServiceAccountSource(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (GoogleGrowthApiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
