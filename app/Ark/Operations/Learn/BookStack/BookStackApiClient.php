<?php

namespace App\Ark\Operations\Learn\BookStack;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BookStackApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenId,
        private readonly string $tokenSecret,
    ) {}

    public static function fromConfig(): self
    {
        $tokenId = (string) config('bookstack.api_token_id');
        $tokenSecret = (string) config('bookstack.api_token_secret');

        if ($tokenId === '' || $tokenSecret === '') {
            throw new RuntimeException('BOOKSTACK_API_TOKEN_ID and BOOKSTACK_API_TOKEN_SECRET must be set.');
        }

        return new self(
            baseUrl: (string) config('bookstack.base_url'),
            tokenId: $tokenId,
            tokenSecret: $tokenSecret,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShelves(): array
    {
        return $this->request('get', '/api/shelves')['data'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBooks(): array
    {
        return $this->request('get', '/api/books')['data'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPages(): array
    {
        return $this->request('get', '/api/pages')['data'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShelf(array $payload): array
    {
        return $this->request('post', '/api/shelves', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateShelf(int $id, array $payload): array
    {
        return $this->request('put', "/api/shelves/{$id}", $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createBook(array $payload): array
    {
        return $this->request('post', '/api/books', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPage(array $payload): array
    {
        return $this->request('post', '/api/pages', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePage(int $id, array $payload): array
    {
        return $this->request('put', "/api/pages/{$id}", $payload);
    }

    public function deletePage(int $id): void
    {
        $this->request('delete', "/api/pages/{$id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function readPage(int $id): array
    {
        return $this->request('get', "/api/pages/{$id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadGalleryImage(int $pageId, string $path, ?string $name = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Image not found: {$path}");
        }

        $response = $this->client()
            ->attach('image', file_get_contents($path), $name ?? basename($path))
            ->post($this->url('/api/image-gallery'), [
                'type' => 'gallery',
                'uploaded_to' => $pageId,
                'name' => $name ?? basename($path),
            ]);

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $response = match ($method) {
            'get' => $this->client()->get($this->url($path)),
            'post' => $this->client()->post($this->url($path), $payload ?? []),
            'put' => $this->client()->put($this->url($path), $payload ?? []),
            'delete' => $this->client()->delete($this->url($path)),
            default => throw new RuntimeException("Unsupported HTTP method [{$method}]."),
        };

        if ($method === 'delete' && $response->status() === 204) {
            return [];
        }

        return $this->decode($response);
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Token '.$this->tokenId.':'.$this->tokenSecret,
            ])
            ->timeout(120)
            ->retry(5, 3000);
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("BookStack API error ({$response->status()}): {$message}");
        }

        return $response->json() ?? [];
    }
}
