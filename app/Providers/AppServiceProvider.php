<?php

namespace App\Providers;

use App\Ark\Communications\Provisioning\ProvisionBuilder;
use App\Ark\Mobile\Push\DispatchMobilePushForInboundMessage;
use App\Ark\Mobile\Push\DispatchMobilePushForOperationalEvent;
use App\Ark\Mobile\Push\NotifyMobileLifecyclePushAction;
use App\Ark\Mobile\Push\PushTransport;
use App\Ark\Mobile\Push\Transport\Firebase\FirebasePushTransport;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Commands\OperationsCommandRegistry;
use App\Ark\Operations\Commands\RegisterCoreOperationsCommands;
use App\Ark\Operations\Documents\HeadlessChromiumPdfRenderer;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Documents\PdfRuntimeConfigurator;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Learn\LearnArkProgressResolver;
use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use App\Ark\Operations\Parts\Contracts\PartsCatalogLauncher;
use App\Ark\Operations\Parts\NotConfiguredPartsCatalogLauncher;
use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use App\Ark\Operations\Payments\FakeSquarePaymentsClient;
use App\Ark\Operations\Payments\SquareAdapterMissingClient;
use App\Ark\Operations\Payments\SquareApiPaymentsClient;
use App\Ark\Operations\Payments\SquareConfiguration;
use App\Ark\Operations\Payments\SquareSdk;
use App\Ark\Operations\Recommendations\RecommendationWorkCompletionListener;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopIntegrationRuntimeConfig;
use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;
use App\Ark\Operations\Telephony\Providers\NotConfiguredTelephonyProvider;
use App\Ark\Operations\Workspace\WorkspaceTabBootEnricher;
use App\Ark\Platform\Provisioning\Coolify\CoolifyAdapter;
use App\Ark\Platform\Provisioning\Coolify\CoolifyClient;
use App\Ark\Platform\Provisioning\Coolify\CoolifyDeploymentMapper;
use App\Ark\Platform\Provisioning\Coolify\CoolifyExecutionStore;
use App\Ark\Platform\Provisioning\Coolify\FakeCoolifyClient;
use App\Ark\Platform\Provisioning\Coolify\HttpCoolifyClient;
use App\Ark\Platform\VoiceTransportRuntimeConfig;
use App\Ark\Runtime\Broadcast\ReverbDeployment;
use App\Ark\Runtime\Surfaces\PublicRootUrlConfigurator;
use App\Ark\Vehicles\Providers\NhtsaProvider;
use App\Ark\Vehicles\VehicleIntelligenceManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pre-DB first-run: avoid database session/cache before installer configures MySQL.
        if (\App\Ark\Install\InstallationState::isNotInstalled()) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }

        $this->app->scoped(ShopIntegrationCredentials::class, fn (): ShopIntegrationCredentials => ShopIntegrationCredentials::forCurrentShop());
        $this->app->bind(OutboundSmsTransport::class, NotConfiguredOutboundSmsTransport::class);
        $this->app->bind(TelephonyProvider::class, NotConfiguredTelephonyProvider::class);
        $this->app->scoped(RepairOrderStatusCatalog::class);

        // Default Fake. Optional ark/payments-square ServiceProvider rebinds to the live SDK client.
        // If credentials look configured but the adapter is missing, fail loudly (not silent Fake charges).
        $this->app->bind(SquarePaymentsClient::class, function (): SquarePaymentsClient {
            if (app()->environment('testing')) {
                return new FakeSquarePaymentsClient;
            }

            if (! SquareSdk::adapterPackagePresent()) {
                $square = app(SquareConfiguration::class);

                if ($square->enabled() || $square->configured()) {
                    return new SquareAdapterMissingClient;
                }

                return new FakeSquarePaymentsClient;
            }

            // Package provider should have rebound this; keep a safe fallback if discovery order differs.
            if (! app(SquareConfiguration::class)->configured()) {
                return new FakeSquarePaymentsClient;
            }

            return app(SquareApiPaymentsClient::class);
        });

        $this->app->singleton(ProvisionBuilder::class, fn (): ProvisionBuilder => ProvisionBuilder::default());

        $this->app->bind(CoolifyClient::class, function (): CoolifyClient {
            if (app()->environment('testing')) {
                return new FakeCoolifyClient;
            }

            $enabled = (bool) config('ark-platform.coolify.enabled', false);
            $token = (string) config('ark-platform.coolify.token', '');

            if (! $enabled || $token === '') {
                return new FakeCoolifyClient;
            }

            return new HttpCoolifyClient(
                (string) config('ark-platform.coolify.base_url'),
                $token,
                (int) config('ark-platform.coolify.timeout', 15),
                (int) config('ark-platform.coolify.connect_timeout', 5),
            );
        });

        $this->app->bind(CoolifyAdapter::class, function ($app): CoolifyAdapter {
            $enabled = (bool) config('ark-platform.coolify.enabled', false);

            return new CoolifyAdapter(
                client: $app->make(CoolifyClient::class),
                mapper: $app->make(CoolifyDeploymentMapper::class),
                execution: $app->make(CoolifyExecutionStore::class),
                milestone: max(1, min(5, (int) config('ark-platform.coolify.milestone', 1))),
                applicationUuid: config('ark-platform.coolify.application_uuid'),
                enabled: $enabled,
                pollIntervalSeconds: (int) config('ark-platform.coolify.poll_interval_seconds', 2),
                pollTimeoutSeconds: (int) config('ark-platform.coolify.poll_timeout_seconds', 60),
                allowDisabledSuccess: app()->environment('testing', 'local') && ! $enabled,
            );
        });

        $this->app->bind(PartsCatalogLauncher::class, NotConfiguredPartsCatalogLauncher::class);

        $this->app->bind(PdfRenderer::class, HeadlessChromiumPdfRenderer::class);
        $this->app->bind(VehicleIntelligenceManager::class, fn ($app) => new VehicleIntelligenceManager(
            [$app->make(NhtsaProvider::class)],
        ));

        $this->app->bind(PushTransport::class, FirebasePushTransport::class);

        $this->app->scoped(OperationsCommandRegistry::class, function ($app): OperationsCommandRegistry {
            $registry = new OperationsCommandRegistry;
            $app->make(RegisterCoreOperationsCommands::class)($registry);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PublicRootUrlConfigurator::apply();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        PdfRuntimeConfigurator::apply();
        ShopIntegrationRuntimeConfig::apply();
        VoiceTransportRuntimeConfig::apply();
        ShopDisplayTimezone::apply();

        RateLimiter::for('portal-token-read', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('portal-token-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->ip());
        });

        if (! $this->app->environment('testing') && ($warning = ReverbDeployment::hostMismatchWarning()) !== null) {
            Log::warning($warning);
        }

        View::composer('components.operations.app', function ($view): void {
            $user = auth()->user();
            $request = request();
            $learnTrainingSnooze = null;
            $canSnoozeTraining = false;

            if ($user !== null) {
                $shell = app(LearnArkProgressResolver::class)
                    ->shellProjectionFor($user);

                $learnTrainingSnooze = $shell->isCurrent ? null : $shell->snoozeState;
                $canSnoozeTraining = $shell->canSnoozeTraining;
            }

            $view->with('learnTrainingSnooze', $learnTrainingSnooze);
            $view->with('canSnoozeTraining', $canSnoozeTraining);

            if (! config('ark_workspace_tabs.enabled', true)) {
                $view->with('arkWorkspaceEntity', null);

                return;
            }

            $boot = $request?->attributes->get('operations.workspace_tab_boot');

            if (! is_array($boot)) {
                $boot = app(WorkspaceTabBootEnricher::class)->forRequest($request);
            }

            $view->with('arkWorkspaceEntity', $boot);
        });

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            if ($event->message->getReplyTo() !== []) {
                return;
            }

            $address = config('mail.reply_to.address');

            if (! filled($address)) {
                return;
            }

            $event->message->replyTo(
                $address,
                config('mail.reply_to.name') ?: null,
            );
        });

        Event::listen(
            ConversationMessageReceived::class,
            DispatchMobilePushForInboundMessage::class,
        );

        OperationalEvent::created(DispatchMobilePushForOperationalEvent::class);

        ApprovalEvent::created(function (ApprovalEvent $event): void {
            app(NotifyMobileLifecyclePushAction::class)->forEstimateApproved($event);
        });

        OperationalEvent::created(RecommendationWorkCompletionListener::class);
    }
}
