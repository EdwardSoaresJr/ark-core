<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
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
    ) {}

    public function canRead(): bool
    {
        return $this->client->configured();
    }

    /**
     * @return list<PartsTechQuoteLine>
     */
    public function linesForRepairOrder(RepairOrder $repairOrder): array
    {
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
}
