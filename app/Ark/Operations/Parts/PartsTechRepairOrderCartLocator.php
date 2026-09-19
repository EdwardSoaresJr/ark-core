<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Finds the PartsTech cart that actually contains quote lines for a repair order.
 */
final class PartsTechRepairOrderCartLocator
{
    public function __construct(
        private readonly PartsTechHttpClient $client,
        private readonly PartsTechCatalogLauncher $launcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quoteCartForRepairOrder(RepairOrder $repairOrder): array
    {
        $cartId = $this->resolveQuoteCartId($repairOrder);

        return $this->fetchQuoteCart($cartId);
    }

    public function findCartIdWithMostParts(RepairOrder $repairOrder, ?string $excludeCartId = null): ?string
    {
        $best = $this->findBestMatchingCartSummary($repairOrder, $excludeCartId);

        if ($best === null || $best['itemCount'] === 0) {
            return null;
        }

        return $best['id'];
    }

    /**
     * Another RO's cart is active with parts — shared shop login is mid-session elsewhere.
     *
     * @return array{id: string, repairOrderNumber: string, itemCount: int}|null
     */
    public function foreignActiveCartHold(RepairOrder $repairOrder): ?array
    {
        $active = $this->activeCartSummary();

        if ($active['id'] === '' || $active['itemCount'] === 0) {
            return null;
        }

        if (PartsTechShopReference::matchesCartReference($active['repairOrderNumber'], $repairOrder)) {
            return null;
        }

        return $active;
    }

    public function shopSessionLockMessage(RepairOrder $repairOrder, ?array $foreignActive = null): string
    {
        $foreignActive ??= $this->foreignActiveCartHold($repairOrder);

        if ($foreignActive === null) {
            return '';
        }

        $blocking = trim((string) $foreignActive['repairOrderNumber']);
        $expected = PartsTechShopReference::cartReference($repairOrder);
        $partLabel = $foreignActive['itemCount'] === 1 ? 'part' : 'parts';

        return 'PartsTech is actively shopping '.$blocking.' ('.$foreignActive['itemCount'].' '.$partLabel.' in cart). '
            .'Finish and pull that quote, or clear the cart in PartsTech, before opening or pulling '.$expected.'. '
            .($this->client->usesPersonalLogin()
                ? 'Your PartsTech login can only have one active cart at a time.'
                : 'ARK uses one shared PartsTech shop login — only one repair order should be in PartsTech at a time.');
    }

    public function activateCart(string $cartId): void
    {
        $this->client->graphql(<<<'GRAPHQL'
            mutation ActivateCart($input: ActivateCartInput!) {
              activateCart(input: $input) {
                cart {
                  id
                  active
                }
              }
            }
            GRAPHQL, [
            'input' => [
                'id' => $cartId,
            ],
        ]);
    }

    /**
     * @return array{id: string, repairOrderNumber: string, itemCount: int}
     */
    public function activeCartSummary(): array
    {
        return $this->summarizeCart($this->fetchActiveSnapshot());
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchQuoteCart(string $cartId): array
    {
        $response = $this->client->graphql(<<<'GRAPHQL'
            query PartsTechCartQuote($id: ID!) {
              cart(id: $id) {
                id
                repairOrderNumber
                orders {
                  id
                  supplier {
                    name
                  }
                  items {
                    id
                    quantity
                    partNumber
                    partName
                    brand {
                      name
                    }
                    builtItem {
                      product {
                        partNumberDisplay
                        title
                        price
                        customerPrice
                        listPrice
                        attributes {
                          name
                          value
                        }
                      }
                    }
                  }
                }
              }
            }
            GRAPHQL, [
            'id' => $cartId,
        ]);

        $cart = data_get($response, 'data.cart');

        if (! is_array($cart) || ! filled($cart['id'] ?? null)) {
            throw new \RuntimeException('PartsTech cart '.$cartId.' could not be loaded.');
        }

        return $cart;
    }

    private function resolveQuoteCartId(RepairOrder $repairOrder): string
    {
        $active = $this->activeCartSummary();

        if ($active['id'] !== '' && PartsTechShopReference::matchesCartReference($active['repairOrderNumber'], $repairOrder) && $active['itemCount'] > 0) {
            return $active['id'];
        }

        $excludeActiveId = null;

        if ($active['id'] !== '' && $this->foreignActiveCartHold($repairOrder) !== null) {
            $excludeActiveId = $active['id'];
        } elseif ($active['id'] !== '') {
            $excludeActiveId = $active['id'];
        }

        $bestId = $this->findCartIdWithMostParts($repairOrder, $excludeActiveId);

        if ($bestId !== null) {
            return $bestId;
        }

        $foreignHold = $this->foreignActiveCartHold($repairOrder);

        if ($foreignHold !== null) {
            throw new PartsTechShopSessionLockedException(
                trim((string) $foreignHold['repairOrderNumber']),
                $this->shopSessionLockMessage($repairOrder, $foreignHold),
            );
        }

        if ($active['id'] !== '' && PartsTechShopReference::matchesCartReference($active['repairOrderNumber'], $repairOrder)) {
            return $active['id'];
        }

        $login = $this->client->loginUsername();

        throw new \RuntimeException(
            'No PartsTech cart found for '.$this->launcher->partsTechCartReference($repairOrder).'. '
            .'Open PartsTech from this RO while signed in as '.$login.', add parts, then pull again.'
        );
    }

    /**
     * @return array{id: string, repairOrderNumber: string, itemCount: int}|null
     */
    private function findBestMatchingCartSummary(RepairOrder $repairOrder, ?string $excludeCartId = null): ?array
    {
        $best = null;

        foreach ($this->searchTermCandidates($repairOrder) as $search) {
            foreach ($this->fetchCartSearchResults($search) as $summary) {
                if ($excludeCartId !== null && $summary['id'] === $excludeCartId) {
                    continue;
                }

                if (! PartsTechShopReference::matchesCartReference($summary['repairOrderNumber'], $repairOrder)) {
                    continue;
                }

                if ($best === null || $summary['itemCount'] > $best['itemCount']) {
                    $best = $summary;
                }
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function searchTermCandidates(RepairOrder $repairOrder): array
    {
        return array_values(array_unique([
            $this->launcher->partsTechCartReference($repairOrder),
            $this->launcher->repairOrderNumber($repairOrder),
        ]));
    }

    /**
     * @return list<array{id: string, repairOrderNumber: string, itemCount: int}>
     */
    private function fetchCartSearchResults(string $search): array
    {
        $response = $this->client->graphql(<<<'GRAPHQL'
            query PartsTechCartsSearch($search: String!) {
              carts(search: $search, first: 15) {
                edges {
                  node {
                    id
                    repairOrderNumber
                    orders {
                      items {
                        id
                        quantity
                        partNumber
                        partName
                      }
                    }
                  }
                }
              }
            }
            GRAPHQL, [
            'search' => $search,
        ]);

        $summaries = [];

        foreach (data_get($response, 'data.carts.edges', []) as $edge) {
            $node = data_get($edge, 'node');

            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }

            $summaries[] = $this->summarizeCart($node);
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchActiveSnapshot(): array
    {
        $response = $this->client->graphql(<<<'GRAPHQL'
            query {
              activeCart {
                id
                repairOrderNumber
                orders {
                  items {
                    id
                    quantity
                    partNumber
                    partName
                  }
                }
              }
            }
            GRAPHQL);

        $activeCart = data_get($response, 'data.activeCart');

        return is_array($activeCart) ? $activeCart : [];
    }

    /**
     * @param  array<string, mixed>  $cart
     * @return array{id: string, repairOrderNumber: string, itemCount: int}
     */
    private function summarizeCart(array $cart): array
    {
        return [
            'id' => (string) ($cart['id'] ?? ''),
            'repairOrderNumber' => (string) ($cart['repairOrderNumber'] ?? ''),
            'itemCount' => PartsTechCartItems::liveCount($cart),
        ];
    }
}
