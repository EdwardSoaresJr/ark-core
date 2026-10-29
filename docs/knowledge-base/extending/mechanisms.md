---
title: Extension mechanisms
description: Each ARK Core extension point that exists today, with a working pattern and the update risk.
---

# Extension mechanisms

<!-- source: app/Providers/AppServiceProvider.php -->

For each mechanism: whether Core supports it today, where the point is, a pattern that matches the code, and what an upgrade can break.

## Service providers

**Supported.** Laravel provider discovery is the loader. There is no `app/Plugins` scan.

Application providers are the array in `bootstrap/providers.php`. A package adds itself through Composer `extra.laravel.providers`. Core will register that provider on boot without a patch, as long as the package is installed and discovery is not disabled.

**Breaks when:** a Core upgrade renames a class your provider type-hints.

## Container bindings

**Supported as ordinary Laravel rebinding.** Stock bindings are in `AppServiceProvider::register()`.

```php
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Telephony\Contracts\TelephonyProvider;

public function register(): void
{
    $this->app->bind(PdfRenderer::class, YourPdfRenderer::class);
}
```

`YourPdfRenderer` must implement `PdfRenderer`. Confirm in a test that the container returns your class after the application boots. If a later provider binds the same interface, that later binding wins. Application providers in `bootstrap/providers.php` are part of Core. Keep your provider in a package so you are not maintaining a fork of `AppServiceProvider` only to swap one line.

**Breaks when:** the interface gains a method, or Core starts resolving a concrete class instead of the interface.

`PartsCatalogLauncher` today is only `configured()`, `poNumber()`, and `blockedReason()`. Implementing it does not give you a parts-ordering protocol. `TelephonyProvider` parses an incoming voice request and builds a voice response. Binding it does not create a phone carrier.

`OutboundSmsTransport` is bound to the platform implementation. Replacing it is code you own. It is not the Hosted texting product, and this site does not publish a partner SMS API.

## Events and listeners

**Supported.** `AppServiceProvider::boot()` shows the pattern Core itself uses:

```php
Event::listen(
    ConversationMessageReceived::class,
    DispatchMobilePushForInboundMessage::class,
);
```

`ConversationMessageReceived` is a real event. Its constructor takes a payload array. It broadcasts now on a private conversation channel as `conversation.message.received`. A listener can implement `handle(ConversationMessageReceived $event)`.

Model events are also in use:

```php
OperationalEvent::created(DispatchMobilePushForOperationalEvent::class);
ApprovalEvent::created(function (ApprovalEvent $event): void {
    // Core notifies mobile devices from here.
});
```

Register additional listeners from your provider's `boot()` method. A full example is [Listen for a domain event](/tutorials/listen-for-a-domain-event).

**Breaks when:** the payload keys change. Treat the payload as internal unless a Core test freezes it.

There is no event catalog file. Search `Event::listen` and `::created(` before claiming a domain event exists.

## Routes, middleware, and controllers

**Supported for additions.** Your provider can load a route file:

```php
use Illuminate\Support\Facades\Route;

public function boot(): void
{
    Route::middleware('web')
        ->group(__DIR__.'/../routes/web.php');
}
```

New routes should use your own URI prefix, not `/api/mobile` or an existing staff path. Overriding a Core route by registering the same URI is load-order dependent and will break.

Middleware aliases registered by Core are in `bootstrap/app.php`: `permission`, `role`, `role_or_permission`, `front_door`, `portal.noindex`, `appointments.surface`, `owner.workspace`, `business.workspace`, `api.staff`. You can append middleware in your provider with the router. You cannot add an alias to Core's map without editing `bootstrap/app.php`.

**Breaks when:** you copied a Core controller into your package and Core changes the action that controller called.

## Views and Blade

**Supported for new views.** `loadViewsFrom` in a provider publishes a namespace:

```php
$this->loadViewsFrom(__DIR__.'/../resources/views', 'your-package');
```

Render `your-package::widget`. Replacing `resources/views/operations/...` is a fork of that Blade file. There is no theme directory and no `View::addNamespace` override convention documented as stable.

`View::composer` can attach data to an existing view name. Core already composes `components.operations.app`. Another composer on the same view runs as well. Do not assume you can remove Core's data.

**Breaks when:** the view name or the variables it expects change.

## Frontend assets

**Partial.** The shop bundle is Vite, rooted at the application `package.json`. A package does not get a slot in that build. Practical options today:

- Serve a small amount of CSS from your own route or a published asset.
- Edit the Core Vite entry, which is a fork of the frontend build.

There is no widget region, theme token API, or asset pipeline hook for third-party packages.

## Artisan commands

**Supported.** Classes in `app/Console/Commands` are discovered by Laravel. A package registers commands from its provider:

```php
if ($this->app->runningInConsole()) {
    $this->commands([
        \Your\Package\HelloCoreCommand::class,
    ]);
}
```

See [Add an Artisan command](/tutorials/add-an-artisan-command).

**Breaks when:** you depend on a Core command's output text. That text is not a parser contract.

## Scheduled tasks

**Partial.** The schedule is a closure inside `bootstrap/app.php`. A package cannot append to that closure. You can schedule from your provider:

```php
use Illuminate\Support\Facades\Schedule;

public function boot(): void
{
    Schedule::command('your-package:sync')->hourly();
}
```

That works on current Laravel because `Schedule` is available during boot. Verify it in your package's test. Core's own tasks will not call your code unless you edited `bootstrap/app.php`.

## Queue jobs

**Supported.** Implement `ShouldQueue` and dispatch the job. The shop's queue connection decides whether it runs inline (`sync`) or in Horizon. A job that assumes Redis is wrong on a default PHP install.

**Breaks when:** the worker code and the web code are different checkouts. Deploy them together.

## Migrations and models

**Supported for new tables.** Load migrations from the package:

```php
$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
```

Use your own table prefix. Do not alter Core tables from a package migration. A later Core migration may touch the same columns and will not know about your change.

Adding a relationship by subclassing a Core model is fragile. Prefer a table that stores the Core model's id.

**Breaks when:** Core renames the id column or changes deletion behavior. `repair_orders.id` versus the public `repair_order_id` shown in API projections are not interchangeable. The mobile projection returns `repair_order_id` as `id`.

## Policies

**Supported as Laravel policies and gates you register.** Core's day-to-day checks are Spatie permissions plus explicit `abort_unless` in controllers, not a policy class on every model. Registering `Gate::policy` does not wrap those `abort_unless` calls. To enforce a new rule inside an existing Core action you must change that action or listen after the fact and reject downstream. You cannot transparently wrap every controller.

## Operations command palette

**Internal.** `OperationsCommandRegistry` is a scoped list of navigation commands built by `RegisterCoreOperationsCommands`. It is not a plugin API. A container `extend()` can append a command after you resolve the class and confirm the instance is shared for the request. Treat that as unsupported compatibility. The class comment says the palette does not own business logic.

## Feature-specific extensions

| Idea | Reality |
| --- | --- |
| Dashboard widget | No region or view hook. Proposed only. |
| New report in the owner report list | The report catalog is application code. Proposed only. |
| Inspection step injected into the checklist | Templates are data. A new capture field in the Tech API is a Core change. |
| Notification channel | Listen for `ConversationMessageReceived` or `ApprovalEvent` being created. There is no notification-channel registry. |
| Custom Dragon tool | Dragon tools are classes Core registers. Adding one is a Core change unless a future registrar exists. It does not exist as a public list in `AppServiceProvider`. |

Those proposed items are written up so they are not confused with working tutorials: [Proposed, not implemented](/tutorials/proposed).
