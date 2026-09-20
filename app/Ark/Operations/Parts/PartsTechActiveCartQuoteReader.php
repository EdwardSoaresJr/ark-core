<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\Parts\ArkPartsClient;
use App\Ark\Platform\Parts\PartsTechPlatformGateway;
use App\Ark\Platform\Parts\PartsTechPlatformRequest;
use App\Models\User;
use RuntimeException;

/**
 * Reads the PartsTech active cart quote (saved cart lines) for import into ARK.
 */
final class PartsTechActiveCartQuoteReader
{
    public function __construct(
        private readonly PartsTechHttpClient $client,
        private readonly PartsTechCatalogLauncher $launcher,
        private readonly PartsTechRepairOrderCartLocator $cartLocator,
        private readonly PartsTechPlatformGateway $platform,
        private readonly ArkPartsClient $parts,
    ) {}

    public function canRead(): bool
    {
        if ($this->platform->usesPlatform()) {
            return $this->platform->isReady();
        }

        return $this->client->configured();
    }

    /**
     * @return list<PartsTechQuoteLine>
     */
    public function linesForRepairOrder(RepairOrder $repairOrder): array
    {
        if ($this->platform->usesPlatform()) {
            return $this->linesViaPlatform($repairOrder);
        }

        if (! $this->canRead()) {
            throw new RuntimeException('PartsTech shop credentials are not configured.');
        }

        $repairOrder->refresh();
        $expectedReference = $this->launcher->partsTechCartReference($repairOrder);

        $this->client->login();

        $activeCart = $this->cartLocator->quoteCartForRepairOrder($repairOrder);

        $cartNumber = trim((string) ($activeCart['repairOrderNumber'] ?? ''));

        if ($cartNumber !== '' && ! PartsTechShopReference::matchesCartReference($cartNumber, $repairOrder)) {
            throw new RuntimeException(
                'PartsTech active cart is for '.$cartNumber.', not '.$expectedReference.'. Open PartsTech from this repair order and save the quote there first.',
            );
        }

        $lines = [];

        foreach (data_get($activeCart, 'orders', []) as $order) {
            if (! is_array($order)) {
                continue;
            }

            $vendorName = trim((string) data_get($order, 'supplier.name', ''));

            foreach (data_get($order, 'items', []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $normalized = $this->normalizeItem($item, $vendorName);

                if ($normalized !== null) {
                    $lines[] = $normalized;
                }
            }
        }

        if ($lines === []) {
            $login = $this->client->loginUsername();

            throw new RuntimeException(
                'PartsTech cart '.$expectedReference.' is open but has no parts to import. '
                .'Add parts in PartsTech (signed in as '.$login.'), save the quote, then pull again.'
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function normalizeItem(array $item, string $vendorName): ?PartsTechQuoteLine
    {
        $product = data_get($item, 'builtItem.product', []);
        $product = is_array($product) ? $product : [];

        $partNumber = trim((string) (
            $item['partNumber']
            ?? $product['partNumberDisplay']
            ?? ''
        ));

        $description = trim((string) (
            $item['partName']
            ?? $product['title']
            ?? ''
        ));

        $brandName = trim((string) data_get($item, 'brand.name', ''));

        if ($description === '' && $partNumber !== '') {
            $description = $partNumber;
        }

        if ($description === '') {
            return null;
        }

        if ($brandName !== '' && ! str_contains(strtolower($description), strtolower($brandName))) {
            $description = $brandName.' '.$description;
        }

        $positionLabel = PartsTechQuoteLineAttributes::positionLabel($item);
        $description = PartsTechQuoteLineAttributes::labeledDescription($description, $positionLabel);
        $description = mb_substr($description, 0, 255);

        $quantity = (float) ($item['quantity'] ?? 1);

        if ($quantity <= 0) {
            $quantity = 1;
        }

        $partCost = $this->resolvePartCostDollars($product);

        $sourcingNotes = 'Imported from PartsTech';

        if ($vendorName !== '') {
            $sourcingNotes .= ' · '.$vendorName;
        }

        $itemId = trim((string) ($item['id'] ?? ''));
        $sourceKey = $itemId !== ''
            ? 'pt:'.$itemId
            : 'pt:'.hash('sha256', implode('|', [
                $partNumber,
                $vendorName,
                $description,
                number_format($quantity, 2, '.', ''),
                number_format($partCost, 2, '.', ''),
            ]));

        return new PartsTechQuoteLine(
            sourceKey: $sourceKey,
            description: $description,
            quantity: number_format($quantity, 2, '.', ''),
            partCost: number_format($partCost, 2, '.', ''),
            partNumber: $partNumber !== '' ? $partNumber : null,
            vendorName: $vendorName !== '' ? $vendorName : null,
            sourcingNotes: mb_substr($sourcingNotes, 0, 1000),
            positionLabel: $positionLabel,
            brandName: $brandName !== '' ? $brandName : null,
        );
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function resolvePartCostDollars(array $product): float
    {
        $customerPrice = $product['customerPrice'] ?? null;
        $price = $product['price'] ?? null;

        if (is_numeric($customerPrice) && (float) $customerPrice > 0) {
            return (float) $customerPrice;
        }

        if (is_numeric($price) && (float) $price >= 0) {
            return (float) $price;
        }

        return 0.0;
    }

    /**
     * @return list<PartsTechQuoteLine>
     */
    private function linesViaPlatform(RepairOrder $repairOrder): array
    {
        if (! $this->platform->isReady()) {
            throw new RuntimeException($this->platform->blockedReason());
        }

        $user = auth()->user();
        $cartReference = $this->launcher->partsTechCartReference($repairOrder);
        $result = $this->parts->quoteLines(
            $cartReference,
            PartsTechPlatformRequest::payload(
                $repairOrder,
                $this->launcher,
                $user instanceof User ? $user : null,
            ),
        );

        if (($result['cart_locked'] ?? false) === true || (int) ($result['http_status'] ?? 0) === 423) {
            throw new PartsTechShopSessionLockedException(
                trim((string) ($result['blocking_cart_reference'] ?? '')),
                trim((string) ($result['message'] ?? 'PartsTech is busy on another repair order.')),
            );
        }

        if (($result['ok'] ?? false) !== true) {
            $message = trim((string) ($result['message'] ?? 'PartsTech quote could not be loaded.'));
            if (
                $user instanceof User
                && $user->usesPersonalPartsTechLogin()
                && $this->isPartsTechSeatAuthFailure($message)
            ) {
                $message = trim((string) $user->partstech_username).'\'s PartsTech login needs attention. '.$message;
            }

            throw new RuntimeException($message);
        }

        $lines = [];
        foreach ($result['lines'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = $this->lineFromPlatformRow($row);
            if ($mapped !== null) {
                $lines[] = $mapped;
            }
        }

        if ($lines === []) {
            throw new RuntimeException(
                'PartsTech cart '.$cartReference.' is open but has no parts to import. Add parts in PartsTech, save the quote, then pull again.'
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function lineFromPlatformRow(array $row): ?PartsTechQuoteLine
    {
        $description = trim((string) ($row['description'] ?? ''));
        $sourceKey = trim((string) ($row['source_key'] ?? ''));
        if ($description === '' || $sourceKey === '') {
            return null;
        }

        $partCost = $row['part_cost'] ?? null;
        if (! is_numeric($partCost) && isset($row['unit_cost_cents']) && is_numeric($row['unit_cost_cents'])) {
            $partCost = number_format(((int) $row['unit_cost_cents']) / 100, 2, '.', '');
        }

        $quantity = $row['quantity'] ?? 1;

        return new PartsTechQuoteLine(
            sourceKey: $sourceKey,
            description: mb_substr($description, 0, 255),
            quantity: is_numeric($quantity) ? number_format((float) $quantity, 2, '.', '') : '1.00',
            partCost: is_numeric($partCost) ? number_format((float) $partCost, 2, '.', '') : '0.00',
            partNumber: filled($row['part_number'] ?? null) ? (string) $row['part_number'] : null,
            vendorName: filled($row['vendor_name'] ?? $row['vendor'] ?? null)
                ? (string) ($row['vendor_name'] ?? $row['vendor'])
                : null,
            sourcingNotes: filled($row['sourcing_notes'] ?? null)
                ? mb_substr((string) $row['sourcing_notes'], 0, 1000)
                : 'Imported from PartsTech',
            positionLabel: filled($row['position_label'] ?? null) ? (string) $row['position_label'] : null,
            brandName: filled($row['brand_name'] ?? null) ? (string) $row['brand_name'] : null,
        );
    }

    private function isPartsTechSeatAuthFailure(string $message): bool
    {
        $haystack = mb_strtolower($message);

        return str_contains($haystack, 'login failed')
            || str_contains($haystack, 'invalid credentials')
            || str_contains($haystack, 'authentication failed')
            || str_contains($haystack, 'unauthorized');
    }
}
