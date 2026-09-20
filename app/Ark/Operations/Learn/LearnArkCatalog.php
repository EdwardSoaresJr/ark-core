<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Operations\Learn\Catalog\LearnArkAdminArticles;
use App\Ark\Operations\Learn\Catalog\LearnArkAdvisorArticles;
use App\Ark\Operations\Learn\Catalog\LearnArkOwnerArticles;
use App\Ark\Operations\Learn\Catalog\LearnArkTechnicianArticles;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class LearnArkCatalog
{
    /**
     * @return array<string, list<array{slug: string, title: string, summary: string, view: string}>>
     */
    public static function articlesByRoleFor(User $user): array
    {
        $catalog = self::articlesByRole();

        return collect(self::visibleSectionsFor($user))
            ->mapWithKeys(fn (LearnArkSection $section): array => [
                $section->key => $catalog[$section->key] ?? [],
            ])
            ->all();
    }

    /**
     * @param  list<string>  $picks  role:slug tokens
     * @return \Illuminate\Support\Collection<int, array{section: LearnArkSection, slug: string, title: string, summary: string, view: string}>
     */
    public static function resolvePrintPicks(User $user, array $picks): \Illuminate\Support\Collection
    {
        return collect($picks)
            ->map(function (string $pick) use ($user): ?array {
                if (! str_contains($pick, ':')) {
                    return null;
                }

                [$roleKey, $slug] = explode(':', $pick, 2);

                return self::articleFor($user, $roleKey, $slug);
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, list<array{slug: string, title: string, summary: string, view: string}>>
     */
    public static function articlesByRole(): array
    {
        return [
            LearnArkOwnerArticles::roleKey() => LearnArkOwnerArticles::all(),
            LearnArkAdvisorArticles::roleKey() => LearnArkAdvisorArticles::all(),
            LearnArkTechnicianArticles::roleKey() => LearnArkTechnicianArticles::all(),
            LearnArkAdminArticles::roleKey() => LearnArkAdminArticles::all(),
        ];
    }

    /**
     * @return list<LearnArkSection>
     */
    public static function visibleSectionsFor(User $user): array
    {
        return LearnArkSection::visibleFor($user);
    }

    /**
     * @return list<ArkRole>
     *
     * @deprecated Use visibleSectionsFor() — retained for backward compatibility in views during transition
     */
    public static function visibleRolesFor(User $user): array
    {
        return collect(self::visibleSectionsFor($user))
            ->map(fn (LearnArkSection $section): ?ArkRole => $section->staffRole())
            ->filter()
            ->values()
            ->all();
    }

    public static function canViewSection(User $user, LearnArkSection $section): bool
    {
        return LearnArkSection::canView($user, $section);
    }

    public static function canViewRole(User $user, ArkRole $role): bool
    {
        return in_array($role, self::visibleRolesFor($user), true);
    }

    /**
     * @return Collection<int, array{section: LearnArkSection, slug: string, title: string, summary: string, view: string}>
     */
    public static function visibleArticlesFor(User $user): Collection
    {
        $catalog = self::articlesByRole();

        return collect(self::visibleSectionsFor($user))
            ->flatMap(function (LearnArkSection $section) use ($catalog): array {
                return collect($catalog[$section->key] ?? [])
                    ->map(fn (array $article): array => [
                        'section' => $section,
                        ...$article,
                    ])
                    ->all();
            })
            ->values();
    }

    /**
     * @return array{section: LearnArkSection, slug: string, title: string, summary: string, view: string}|null
     */
    public static function articleFor(User $user, string $roleKey, string $slug): ?array
    {
        $section = LearnArkSection::fromKey($roleKey);

        if ($section === null || ! self::canViewSection($user, $section)) {
            return null;
        }

        $article = collect(self::articlesByRole()[$section->key] ?? [])
            ->firstWhere('slug', $slug);

        if ($article === null) {
            return null;
        }

        return [
            'section' => $section,
            ...$article,
        ];
    }

    /**
     * @return array{section: LearnArkSection, slug: string, title: string, summary: string, view: string}|null
     */
    public static function defaultArticleFor(User $user): ?array
    {
        return self::visibleArticlesFor($user)->first();
    }
}
