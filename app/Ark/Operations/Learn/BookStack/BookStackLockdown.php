<?php

namespace App\Ark\Operations\Learn\BookStack;

use App\Models\ArkademyContentRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BookStackLockdown
{
    public function isConfigured(): bool
    {
        $connection = config('database.connections.bookstack');

        return is_array($connection)
            && filled($connection['host'] ?? null)
            && filled($connection['database'] ?? null)
            && filled($connection['username'] ?? null);
    }

    public function run(): BookStackLockdownReport
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BookStack database connection is not configured.');
        }

        $report = new BookStackLockdownReport;
        $authorId = $this->resolveDefaultAuthorBookStackUserId();

        if ($authorId === null) {
            throw new RuntimeException(
                'Default ARKademy author not found in BookStack (external_auth_id='
                .config('bookstack.default_author_ark_user_id').').',
            );
        }

        $report->authorBookStackUserId = $authorId;
        [$report->orphansRemoved, $report->orphansSkipped] = $this->removeOrphanOidcUsers();
        [$report->pagesReattributed, $report->revisionsReattributed] = $this->attributeCatalogPages($authorId);
        $report->importTokenReassigned = $this->assignImportTokenTo($authorId);

        return $report;
    }

    public function resolveDefaultAuthorBookStackUserId(): ?int
    {
        $arkUserId = (string) config('bookstack.default_author_ark_user_id');

        $bookStackUserId = DB::connection('bookstack')
            ->table('users')
            ->where('external_auth_id', $arkUserId)
            ->value('id');

        return $bookStackUserId !== null ? (int) $bookStackUserId : null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function removeOrphanOidcUsers(): array
    {
        $arkUserIds = User::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(static fn (int $id): string => (string) $id)
            ->all();

        $orphans = DB::connection('bookstack')
            ->table('users')
            ->whereNotNull('external_auth_id')
            ->where('external_auth_id', '!=', '')
            ->whereNotIn('external_auth_id', $arkUserIds)
            ->get(['id', 'name', 'email', 'external_auth_id']);

        $removed = 0;
        $skipped = 0;

        foreach ($orphans as $orphan) {
            $ownedEntities = (int) DB::connection('bookstack')
                ->table('entities')
                ->where('owned_by', $orphan->id)
                ->count();

            if ($ownedEntities > 0) {
                $skipped++;

                continue;
            }

            DB::connection('bookstack')->table('role_user')->where('user_id', $orphan->id)->delete();
            DB::connection('bookstack')->table('user_invites')->where('user_id', $orphan->id)->delete();
            DB::connection('bookstack')->table('users')->where('id', $orphan->id)->delete();
            $removed++;
        }

        return [$removed, $skipped];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function attributeCatalogPages(int $authorBookStackUserId): array
    {
        $pageIds = ArkademyContentRegistry::query()
            ->where('source_type', 'page')
            ->pluck('bookstack_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($pageIds === []) {
            return [0, 0];
        }

        $pagesUpdated = DB::connection('bookstack')
            ->table('entities')
            ->where('type', 'page')
            ->whereIn('id', $pageIds)
            ->update([
                'created_by' => $authorBookStackUserId,
                'updated_by' => $authorBookStackUserId,
                'owned_by' => $authorBookStackUserId,
            ]);

        $revisionsUpdated = DB::connection('bookstack')
            ->table('page_revisions')
            ->whereIn('page_id', $pageIds)
            ->update(['created_by' => $authorBookStackUserId]);

        return [(int) $pagesUpdated, (int) $revisionsUpdated];
    }

    private function assignImportTokenTo(int $authorBookStackUserId): bool
    {
        $tokenName = (string) config('bookstack.api_token_name', 'ARK Import Service');

        $updated = DB::connection('bookstack')
            ->table('api_tokens')
            ->where('name', $tokenName)
            ->where('user_id', '!=', $authorBookStackUserId)
            ->update(['user_id' => $authorBookStackUserId]);

        return $updated > 0;
    }
}
