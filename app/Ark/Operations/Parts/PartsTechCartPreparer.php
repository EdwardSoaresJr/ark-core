<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\Parts\ArkPartsClient;
use App\Ark\Platform\Parts\PartsTechPlatformGateway;
use App\Ark\Platform\Parts\PartsTechPlatformRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Catalog open stamps R{RO} on the PartsTech cart (and any supplier orders) before the browser loads.
 * A leftover cart from another RO is retagged to this RO — punchout keeps one live cart.
 * Pull-quote (syncOnly) still finds and stamps an existing quote.
 */
final class PartsTechCartPreparer
{
    private ?string $lastFailureMessage = null;

    /** @var list<string> */
    private array $lastWarnings = [];

    /** @var array<string, mixed>|null */
    private ?array $lastPlatformResult = null;

    private int $lastStampedOrderCount = 0;

    public function __construct(
        private readonly PartsTechHttpClient $client,
        private readonly PartsTechCatalogLauncher $launcher,
        private readonly PartsTechRepairOrderCartLocator $cartLocator,
        private readonly PartsTechPlatformGateway $platform,
        private readonly ArkPartsClient $parts,
    ) {}

    public function canPrepare(): bool
    {
        if ($this->platform->usesPlatform()) {
            return $this->platform->isReady();
        }

        return $this->client->configured();
    }

    public function lastFailureMessage(): ?string
    {
        return $this->lastFailureMessage;
    }

    /**
     * @return list<string>
     */
    public function lastWarnings(): array
    {
        return $this->lastWarnings;
    }

    public function actingLoginUsername(): string
    {
        if (is_array($this->lastPlatformResult) && is_string($this->lastPlatformResult['partstech_login'] ?? null) && $this->lastPlatformResult['partstech_login'] !== '') {
            return (string) $this->lastPlatformResult['partstech_login'];
        }

        return $this->client->loginUsername();
    }

    public function usesPersonalLogin(): bool
    {
        if (is_array($this->lastPlatformResult) && isset($this->lastPlatformResult['partstech_login_source'])) {
            return $this->lastPlatformResult['partstech_login_source'] === 'user';
        }

        return $this->client->usesPersonalLogin();
    }

    public function lastCatalogUrl(): ?string
    {
        $url = is_array($this->lastPlatformResult) ? ($this->lastPlatformResult['catalog_url'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function lastStampedOrderCount(): int
    {
        return $this->lastStampedOrderCount;
    }

    public function prepare(RepairOrder $repairOrder, bool $syncOnly = false, bool $forceCartSwitch = false, ?int $concernId = null, bool $stampOnly = false): void
    {
        if ($this->platform->usesPlatform()) {
            $this->prepareViaPlatform($repairOrder, $syncOnly, $forceCartSwitch, $concernId, $stampOnly);

            return;
        }

        if (! $this->canPrepare()) {
            return;
        }

        $this->lastWarnings = [];
        $this->lastStampedOrderCount = 0;

        $repairOrder->refresh();

        $cartReference = $this->launcher->partsTechCartReference($repairOrder);
        $purchaseOrderNumber = $this->launcher->poNumber($repairOrder);

        $this->client->login();

        if ($stampOnly) {
            $this->stampPurchaseOrderOnAssignedActiveCart($repairOrder, $purchaseOrderNumber);

            return;
        }

        if ($syncOnly) {
            $this->releaseForeignCartHoldIfRequested($repairOrder, $forceCartSwitch);
            $this->syncPurchaseOrderOnActiveCart($repairOrder, $cartReference, $purchaseOrderNumber);

            return;
        }

        $this->prepareCatalogLaunch($repairOrder, $cartReference, $purchaseOrderNumber);
    }

    public function prepareOrReport(RepairOrder $repairOrder, bool $syncOnly = false, bool $forceCartSwitch = false, ?int $concernId = null, bool $stampOnly = false): bool
    {
        $this->lastFailureMessage = null;
        $this->lastWarnings = [];
        $this->lastPlatformResult = null;
        $this->lastStampedOrderCount = 0;

        try {
            $this->prepare($repairOrder, $syncOnly, $forceCartSwitch, $concernId, $stampOnly);

            return true;
        } catch (PartsTechShopSessionLockedException $exception) {
            $this->lastFailureMessage = $exception->getMessage();

            throw $exception;
        } catch (RuntimeException $exception) {
            $this->lastFailureMessage = $exception->getMessage();

            Log::warning('parts-tech.cart-prepare-failed', [
                'repair_order_id' => $repairOrder->repair_order_id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function prepareCatalogLaunch(
        RepairOrder $repairOrder,
        string $cartReference,
        string $purchaseOrderNumber,
    ): void {
        $cartId = $this->resolveCatalogCartId($repairOrder, $cartReference, $purchaseOrderNumber);
        $this->stampPreparedCart($cartId, $repairOrder, $cartReference, $purchaseOrderNumber);
    }

    private function resolveCatalogCartId(
        RepairOrder $repairOrder,
        string $cartReference,
        string $purchaseOrderNumber,
    ): string {
        $active = $this->cartLocator->activeCartSummary();

        if ($active['id'] !== '' && PartsTechShopReference::isAssignedCartReference($active['repairOrderNumber'], $repairOrder)) {
            if ($active['itemCount'] === 0) {
                $cartWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder, $active['id']);

                if ($cartWithParts !== null) {
                    $this->cartLocator->activateCart($cartWithParts);

                    return $cartWithParts;
                }
            }

            return $active['id'];
        }

        if ($active['id'] !== '' && $active['itemCount'] > 0) {
            $blocking = trim((string) $active['repairOrderNumber']);
            $this->lastWarnings[] = $blocking !== ''
                ? 'Cart still has leftover parts from '.$blocking.'. They are now tagged to this repair order. Clear them in PartsTech if they do not belong here.'
                : 'Cart still has leftover parts from another repair order. They are now tagged to this repair order. Clear them in PartsTech if they do not belong here.';

            return $active['id'];
        }

        if ($active['id'] !== '') {
            $this->archiveCart($active['id']);

            $remaining = $this->cartLocator->activeCartSummary();

            if ($remaining['id'] !== '' && $remaining['id'] !== $active['id']) {
                $this->archiveCart($remaining['id']);
            }
        }

        $inactiveWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder);

        if ($inactiveWithParts !== null) {
            $this->cartLocator->activateCart($inactiveWithParts);

            return $inactiveWithParts;
        }

        return $this->createCart($cartReference, $purchaseOrderNumber);
    }

    private function stampPreparedCart(
        string $cartId,
        RepairOrder $repairOrder,
        string $cartReference,
        string $purchaseOrderNumber,
    ): void {
        $this->syncCartMetadata($cartId, $repairOrder, $cartReference, $purchaseOrderNumber);
        $this->activateCart($cartId);
        $this->linkVehicleBestEffort($cartId, $repairOrder);
        $this->syncPurchaseOrderNumbers($purchaseOrderNumber);
    }

    private function resolveCartId(RepairOrder $repairOrder, string $repairOrderNumber, string $purchaseOrderNumber): string
    {
        $active = $this->cartLocator->activeCartSummary();

        if ($active['id'] !== '' && PartsTechShopReference::isAssignedCartReference($active['repairOrderNumber'], $repairOrder)) {
            if ($active['itemCount'] > 0) {
                return $active['id'];
            }

            $cartWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder, $active['id']);

            if ($cartWithParts !== null) {
                $this->cartLocator->activateCart($cartWithParts);

                return $cartWithParts;
            }

            return $active['id'];
        }

        if ($active['id'] !== '') {
            if ($active['itemCount'] > 0) {
                $foreignHold = $this->cartLocator->foreignActiveCartHold($repairOrder);

                if ($foreignHold !== null) {
                    throw new PartsTechShopSessionLockedException(
                        trim((string) $foreignHold['repairOrderNumber']),
                        $this->cartLocator->shopSessionLockMessage($repairOrder, $foreignHold),
                    );
                }
            }

            $this->archiveCart($active['id']);

            $remaining = $this->cartLocator->activeCartSummary();

            if ($remaining['id'] !== '' && PartsTechShopReference::isAssignedCartReference($remaining['repairOrderNumber'], $repairOrder)) {
                return $remaining['id'];
            }

            if ($remaining['id'] !== '' && $remaining['itemCount'] > 0) {
                throw new RuntimeException(
                    'PartsTech still has an active cart for '.$remaining['repairOrderNumber'].' with parts in it. Clear it in PartsTech, then try again.',
                );
            }

            if ($remaining['id'] !== '') {
                $this->archiveCart($remaining['id']);
            }
        }

        $inactiveWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder);

        if ($inactiveWithParts !== null) {
            $this->cartLocator->activateCart($inactiveWithParts);

            return $inactiveWithParts;
        }

        return $this->createCart($repairOrderNumber, $purchaseOrderNumber);
    }

    private function syncPurchaseOrderOnActiveCart(
        RepairOrder $repairOrder,
        string $cartReference,
        string $purchaseOrderNumber,
    ): void {
        $active = $this->cartLocator->activeCartSummary();
        $activeCartId = $active['id'];

        if ($activeCartId === '') {
            $cartWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder);

            if ($cartWithParts !== null) {
                $this->stampPreparedCart($cartWithParts, $repairOrder, $cartReference, $purchaseOrderNumber);
            }

            return;
        }

        $foreignHold = $this->cartLocator->foreignActiveCartHold($repairOrder);

        if ($foreignHold !== null) {
            throw new PartsTechShopSessionLockedException(
                trim((string) $foreignHold['repairOrderNumber']),
                $this->cartLocator->shopSessionLockMessage($repairOrder, $foreignHold),
            );
        }

        if (! PartsTechShopReference::isAssignedCartReference($active['repairOrderNumber'], $repairOrder)) {
            if ($active['itemCount'] > 0) {
                $this->stampPreparedCart($activeCartId, $repairOrder, $cartReference, $purchaseOrderNumber);

                return;
            }

            $this->archiveCart($activeCartId);
            $cartWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder);

            if ($cartWithParts !== null) {
                $this->stampPreparedCart($cartWithParts, $repairOrder, $cartReference, $purchaseOrderNumber);
            }

            return;
        }

        if ($active['itemCount'] === 0) {
            $cartWithParts = $this->cartLocator->findCartIdWithMostParts($repairOrder, $activeCartId);

            if ($cartWithParts !== null) {
                $this->cartLocator->activateCart($cartWithParts);
                $activeCartId = $cartWithParts;
            }
        }

        $this->syncCartMetadata($activeCartId, $repairOrder, $cartReference, $purchaseOrderNumber);
        $this->activateCart($activeCartId);
        $this->linkVehicleBestEffort($activeCartId, $repairOrder);
        $this->syncPurchaseOrderNumbers($purchaseOrderNumber);
    }

    private function stampPurchaseOrderOnAssignedActiveCart(RepairOrder $repairOrder, string $purchaseOrderNumber): void
    {
        $active = $this->cartLocator->activeCartSummary();

        if ($active['id'] === '') {
            $this->lastStampedOrderCount = 0;

            return;
        }

        if (! PartsTechShopReference::isAssignedCartReference($active['repairOrderNumber'], $repairOrder)) {
            $cartReference = $this->launcher->partsTechCartReference($repairOrder);
            $this->syncCartMetadata($active['id'], $repairOrder, $cartReference, $purchaseOrderNumber);
            $this->syncPurchaseOrderNumbers($purchaseOrderNumber);

            return;
        }

        $this->syncActiveCartOrderPurchaseOrderNumbers($purchaseOrderNumber);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchActiveCartSnapshot(): array
    {
        $response = $this->client->graphql(<<<'GRAPHQL'
            query {
              activeCart {
                id
                repairOrderNumber
                orders {
                  id
                  items {
                    id
                  }
                }
              }
            }
            GRAPHQL);

        $activeCart = data_get($response, 'data.activeCart');

        return is_array($activeCart) ? $activeCart : [];
    }

    private function syncCartMetadata(string $cartId, RepairOrder $repairOrder, string $repairOrderNumber, string $purchaseOrderNumber): void
    {
        $repairOrder->loadMissing('customer');

        $input = [
            'id' => $cartId,
            'repairOrderNumber' => $repairOrderNumber,
            'notes' => 'ARK RO '.$repairOrderNumber.' · PO '.$purchaseOrderNumber,
        ];

        $customerEmail = trim((string) $repairOrder->customer?->email);

        if ($customerEmail !== '') {
            $input['customerEmail'] = $customerEmail;
        }

        $this->client->graphql(<<<'GRAPHQL'
            mutation UpdateCart($input: UpdateCartInput!) {
              updateCart(input: $input) {
                cart {
                  id
                  repairOrderNumber
                  notes
                  customerEmail
                }
              }
            }
            GRAPHQL, [
            'input' => $input,
        ]);
    }

    private function releaseForeignCartHoldIfRequested(RepairOrder $repairOrder, bool $forceCartSwitch): void
    {
        if (! $forceCartSwitch) {
            return;
        }

        $foreignHold = $this->cartLocator->foreignActiveCartHold($repairOrder);

        if ($foreignHold === null) {
            return;
        }

        $this->archiveCart($foreignHold['id']);
    }

    private function archiveCart(string $cartId): void
    {
        $this->client->graphql(<<<'GRAPHQL'
            mutation ArchiveCart($input: ArchiveCartInput!) {
              archiveCart(input: $input) {
                cart {
                  id
                }
              }
            }
            GRAPHQL, [
            'input' => [
                'id' => $cartId,
            ],
        ]);
    }

    private function createCart(string $repairOrderNumber, string $purchaseOrderNumber): string
    {
        $created = $this->client->graphql(<<<'GRAPHQL'
            mutation CreateCart($input: CreateCartInput!) {
              createCart(input: $input) {
                cart {
                  id
                  repairOrderNumber
                }
                error
              }
            }
            GRAPHQL, [
            'input' => [
                'repairOrderNumber' => $repairOrderNumber,
                'notes' => 'ARK RO '.$repairOrderNumber.' · PO '.$purchaseOrderNumber,
            ],
        ]);

        $cartId = data_get($created, 'data.createCart.cart.id');

        if (filled($cartId)) {
            return (string) $cartId;
        }

        $error = (string) data_get($created, 'data.createCart.error', '');

        if ($error === 'PUNCHOUT_SESSION_HAS_CART') {
            $activeCartId = $this->cartLocator->activeCartSummary()['id'];

            if ($activeCartId !== '') {
                return $activeCartId;
            }
        }

        if ($error !== '') {
            throw new RuntimeException($this->formatCreateCartError($error));
        }

        throw new RuntimeException('PartsTech did not return a cart id.');
    }

    private function formatCreateCartError(string $error): string
    {
        return match ($error) {
            'PUNCHOUT_SESSION_HAS_CART' => 'PartsTech already has an open cart for this shop session. Close or archive it in PartsTech, then try again.',
            default => 'PartsTech could not create a cart ('.$error.').',
        };
    }

    private function syncPurchaseOrderNumbers(string $purchaseOrderNumber): void
    {
        // Set without clearing first — clearing an empty cart hides the PO field in PartsTech until lines exist.
        $this->syncActiveCartPurchaseOrderNumber($purchaseOrderNumber);
        $this->syncActiveCartOrderPurchaseOrderNumbers($purchaseOrderNumber);
    }

    private function syncActiveCartPurchaseOrderNumber(string $purchaseOrderNumber): void
    {
        $response = $this->client->graphql(<<<'GRAPHQL'
            mutation UpdateActiveCartPurchaseOrderNumber($input: UpdateActiveCartPurchaseOrderNumberInput!) {
              updateActiveCartPurchaseOrderNumber(input: $input) {
                __typename
                ... on UpdateCartPurchaseOrderNumberSuccessPayload {
                  updatedCartId
                }
                ... on UpdateCartPurchaseOrderNumberErrorPayload {
                  errorMessage
                }
              }
            }
            GRAPHQL, [
            'input' => [
                'purchaseOrderNumber' => $purchaseOrderNumber,
            ],
        ]);

        $this->assertPurchaseOrderMutationSucceeded(
            $response,
            'data.updateActiveCartPurchaseOrderNumber',
            'PartsTech could not set the cart purchase order number.',
        );
    }

    private function syncActiveCartOrderPurchaseOrderNumbers(string $purchaseOrderNumber): void
    {
        $activeCart = $this->client->graphql(<<<'GRAPHQL'
            query ActiveCartOrders {
              activeCart {
                orders {
                  id
                }
              }
            }
            GRAPHQL);

        $orderIds = collect(data_get($activeCart, 'data.activeCart.orders', []))
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        $this->lastStampedOrderCount = $orderIds->count();

        foreach ($orderIds as $orderId) {
            $response = $this->client->graphql(<<<'GRAPHQL'
                mutation UpdateActiveCartOrderPurchaseOrderNumber($input: UpdateActiveCartOrderPurchaseOrderNumberInput!) {
                  updateActiveCartOrderPurchaseOrderNumber(input: $input) {
                    __typename
                    ... on UpdateOrderPurchaseOrderNumberSuccessPayload {
                      updatedOrderId
                    }
                    ... on UpdateOrderPurchaseOrderNumberErrorPayload {
                      errorMessage
                    }
                  }
                }
                GRAPHQL, [
                'input' => [
                    'orderId' => (string) $orderId,
                    'purchaseOrderNumber' => $purchaseOrderNumber,
                ],
            ]);

            $this->assertPurchaseOrderMutationSucceeded(
                $response,
                'data.updateActiveCartOrderPurchaseOrderNumber',
                'PartsTech could not set the order purchase order number.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function assertPurchaseOrderMutationSucceeded(array $response, string $path, string $fallback): void
    {
        $payload = data_get($response, $path);

        if (! is_array($payload)) {
            throw new RuntimeException($fallback);
        }

        $typename = (string) ($payload['__typename'] ?? '');

        if (str_ends_with($typename, 'SuccessPayload')) {
            return;
        }

        $errorMessage = trim((string) ($payload['errorMessage'] ?? ''));

        throw new RuntimeException($errorMessage !== '' ? $errorMessage : $fallback);
    }

    private function linkVehicleBestEffort(string $cartId, RepairOrder $repairOrder): void
    {
        if (! $this->launcher->hasVehicleIdentity($repairOrder)) {
            $this->lastWarnings[] = 'No VIN or year/make/model on this RO — pick the vehicle manually in PartsTech.';

            return;
        }

        try {
            $resolved = $this->resolvePartsTechVehicle($repairOrder);
            $this->linkVehicleToCart($cartId, $resolved['vehicleId'], $resolved['vin']);

            if ($resolved['approximate']) {
                $label = $this->launcher->ymmSearchPhrase($repairOrder) ?? 'YMM';
                $this->lastWarnings[] = 'PartsTech linked '.$label.' — confirm trim/engine in PartsTech if fitment looks off.';
            }
        } catch (RuntimeException $exception) {
            $identity = $this->launcher->vinForRepairOrder($repairOrder)
                ?? $this->launcher->ymmSearchPhrase($repairOrder)
                ?? 'vehicle';

            $this->lastWarnings[] = 'PartsTech did not link '.$identity.' — select the vehicle manually in PartsTech.';

            Log::warning('parts-tech.cart-vehicle-link-failed', [
                'repair_order_id' => $repairOrder->repair_order_id,
                'identity' => $identity,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{vehicleId: string, vin: ?string, approximate: bool}
     */
    private function resolvePartsTechVehicle(RepairOrder $repairOrder): array
    {
        $vin = $this->launcher->vinForRepairOrder($repairOrder);

        if ($vin !== null) {
            return [
                'vehicleId' => $this->resolveVehicleIdByVin($vin),
                'vin' => $vin,
                'approximate' => false,
            ];
        }

        return $this->resolveVehicleIdByYmm($repairOrder);
    }

    private function linkVehicleToCart(string $cartId, string $vehicleId, ?string $vin): void
    {
        $input = [
            'cartId' => $cartId,
            'vehicleId' => $vehicleId,
        ];

        if ($vin !== null && $vin !== '') {
            $input['vin'] = $vin;
        }

        $this->client->graphql(<<<'GRAPHQL'
            mutation LinkVehicleToCart($input: LinkVehicleToCartInput!) {
              linkVehicleToCart(input: $input) {
                cart {
                  id
                }
              }
            }
            GRAPHQL, [
            'input' => $input,
        ]);
    }

    private function resolveVehicleIdByVin(string $vin): string
    {
        $response = $this->client->graphql(<<<'GRAPHQL'
            query VehiclesByVin($vin: String!) {
              vehicles(vin: $vin) {
                id
                vin
              }
            }
            GRAPHQL, [
            'vin' => $vin,
        ]);

        $vehicles = collect(data_get($response, 'data.vehicles', []))
            ->filter(fn (mixed $vehicle): bool => is_array($vehicle) && filled($vehicle['id'] ?? null));

        $matchedVehicle = $vehicles->first(
            fn (array $vehicle): bool => strtoupper(trim((string) ($vehicle['vin'] ?? ''))) === $vin,
        );

        $vehicleId = data_get($matchedVehicle, 'id') ?? data_get($vehicles->first(), 'id');

        if (! filled($vehicleId)) {
            throw new RuntimeException('PartsTech could not resolve a vehicle id for VIN '.$vin.'.');
        }

        return (string) $vehicleId;
    }

    /**
     * @return array{vehicleId: string, vin: ?string, approximate: bool}
     */
    private function resolveVehicleIdByYmm(RepairOrder $repairOrder): array
    {
        $ymm = $this->launcher->ymmForRepairOrder($repairOrder);
        $search = $this->launcher->ymmSearchPhrase($repairOrder);

        if ($ymm === null || $search === null) {
            throw new RuntimeException('PartsTech could not resolve a vehicle without VIN or year/make/model.');
        }

        $response = $this->client->graphql(<<<'GRAPHQL'
            query VehicleSuggest($search: String!) {
              vehicleSuggest(search: $search) {
                id
                year
                make { name }
                model { name }
                subModel { name }
                engine { name }
              }
            }
            GRAPHQL, [
            'search' => $search,
        ]);

        $candidates = collect(data_get($response, 'data.vehicleSuggest', []))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['id'] ?? null))
            ->filter(function (array $row) use ($ymm): bool {
                if ((int) ($row['year'] ?? 0) !== $ymm['year']) {
                    return false;
                }

                $make = trim((string) data_get($row, 'make.name', ''));
                $model = trim((string) data_get($row, 'model.name', ''));

                return strcasecmp($make, $ymm['make']) === 0
                    && strcasecmp($model, $ymm['model']) === 0;
            })
            ->values();

        if ($candidates->isEmpty()) {
            throw new RuntimeException('PartsTech could not resolve a vehicle for '.$search.'.');
        }

        $scored = $candidates->map(function (array $row) use ($ymm): array {
            $score = 0;
            $subModel = trim((string) data_get($row, 'subModel.name', ''));
            $engine = trim((string) data_get($row, 'engine.name', ''));

            if ($ymm['trim'] !== null && $subModel !== '' && strcasecmp($subModel, $ymm['trim']) === 0) {
                $score += 2;
            } elseif ($ymm['trim'] !== null && $subModel !== '' && str_contains(strtolower($subModel), strtolower($ymm['trim']))) {
                $score += 1;
            }

            if ($ymm['engine'] !== null && $engine !== '') {
                $needle = strtolower($ymm['engine']);
                $haystack = strtolower($engine);

                if ($haystack === $needle || str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                    $score += 2;
                }
            }

            return ['row' => $row, 'score' => $score];
        })->sortByDesc('score')->values();

        $best = $scored->first();
        $bestScore = (int) ($best['score'] ?? 0);
        $ties = $scored->filter(fn (array $entry): bool => (int) $entry['score'] === $bestScore);

        return [
            'vehicleId' => (string) data_get($best, 'row.id'),
            'vin' => null,
            'approximate' => $ties->count() > 1 || ($ymm['trim'] === null && $ymm['engine'] === null && $candidates->count() > 1),
        ];
    }

    private function activateCart(string $cartId): void
    {
        $this->client->graphql(<<<'GRAPHQL'
            mutation ActivateCart($input: ActivateCartInput!) {
              activateCart(input: $input) {
                cart {
                  id
                  active
                  repairOrderNumber
                }
              }
            }
            GRAPHQL, [
            'input' => [
                'id' => $cartId,
            ],
        ]);
    }

    private function prepareViaPlatform(RepairOrder $repairOrder, bool $syncOnly, bool $forceCartSwitch, ?int $concernId, bool $stampOnly = false): void
    {
        $user = auth()->user();
        $result = $this->parts->prepareSession(PartsTechPlatformRequest::payload(
            $repairOrder,
            $this->launcher,
            $user instanceof User ? $user : null,
            [
                'sync_only' => $syncOnly,
                'force_cart_switch' => $forceCartSwitch,
                'concern_id' => $concernId,
                'stamp_only' => $stampOnly,
            ],
        ));

        $this->lastPlatformResult = $result;
        $this->lastStampedOrderCount = (int) ($result['stamped_order_count'] ?? 0);
        $warnings = $result['warnings'] ?? [];
        $this->lastWarnings = is_array($warnings)
            ? array_values(array_filter($warnings, fn (mixed $warning): bool => is_string($warning) && $warning !== ''))
            : [];

        if (($result['cart_locked'] ?? false) === true || (int) ($result['http_status'] ?? 0) === 423) {
            throw new PartsTechShopSessionLockedException(
                trim((string) ($result['blocking_cart_reference'] ?? '')),
                trim((string) ($result['message'] ?? 'PartsTech is busy on another repair order.')),
            );
        }

        if (($result['prepared'] ?? false) === true) {
            return;
        }

        $message = trim((string) ($result['message'] ?? ''));
        if ($message === '') {
            $login = $this->actingLoginUsername();
            $message = 'PartsTech cart could not be prepared.'.($login !== '' ? ' Sign into PartsTech in your browser as '.$login.', then retry.' : '');
        }

        if ($user instanceof User && $user->usesPersonalPartsTechLogin()) {
            $message = $this->personalSeatFailureMessage($user, $message);
        }

        throw new RuntimeException($message);
    }

    private function personalSeatFailureMessage(User $user, string $providerMessage): string
    {
        $username = trim((string) $user->partstech_username);

        return trim($username.'\'s PartsTech login needs attention. '.$providerMessage);
    }
}
