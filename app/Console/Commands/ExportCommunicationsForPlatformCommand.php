<?php

namespace App\Console\Commands;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Console\Command;

/**
 * Export Core conversations for Platform Stage 9 reconcile.
 */
final class ExportCommunicationsForPlatformCommand extends Command
{
    protected $signature = 'ark:communications:export-for-platform
        {--path=storage/app/comms-platform-export.json : Output path}';

    protected $description = 'Export Core Conversation/Message rows for Platform reconcile (Track C Stage 9)';

    public function handle(): int
    {
        $shopPublicId = PlatformConnection::current()->shopPublicId();
        $conversations = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone->value)
            ->with(['messages.attachments', 'links'])
            ->orderBy('id')
            ->get();

        $payload = [
            'shop_public_id' => $shopPublicId,
            'exported_at' => now()->toIso8601String(),
            'conversations' => $conversations->map(function (Conversation $conversation) {
                $customerId = null;
                foreach ($conversation->links as $link) {
                    if ($link->linkable_type === Customer::class
                        || $link->linkable_type === 'customer'
                        || str_ends_with((string) $link->linkable_type, '\\Customer')) {
                        $customerId = (int) $link->linkable_id;
                        break;
                    }
                }

                return [
                    'contact_address' => $conversation->contact_address,
                    'core_customer_id' => $customerId,
                    'messages' => $conversation->messages->map(function (ConversationMessage $message) {
                        $meta = is_array($message->metadata) ? $message->metadata : [];

                        return [
                            'direction' => $message->direction instanceof \BackedEnum
                                ? $message->direction->value
                                : (string) $message->direction,
                            'body' => $message->body,
                            'occurred_at' => optional($message->occurred_at)?->toIso8601String(),
                            'provider_message_id' => $meta['provider_message_id']
                                ?? $meta['twilio_message_sid']
                                ?? null,
                            'delivery_status' => $meta['delivery_status'] ?? null,
                            'to_phone' => $meta['to_number'] ?? null,
                            'media' => $message->attachments->map(static fn ($a) => [
                                'url' => $a->provider_url,
                                'content_type' => $a->content_type,
                                'provider_media_sid' => $a->provider_media_sid,
                                'byte_size' => $a->byte_size,
                            ])->filter(fn ($m) => filled($m['url'] ?? null))->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];

        $path = (string) $this->option('path');
        $full = base_path($path);
        if (str_starts_with($path, 'storage/')) {
            $full = storage_path(substr($path, strlen('storage/')));
        } elseif (! str_starts_with($path, '/')) {
            $full = base_path($path);
        } else {
            $full = $path;
        }

        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($full, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info('Wrote '.$full.' ('.count($payload['conversations']).' conversations)');

        return self::SUCCESS;
    }
}
