<?php

use App\Ark\Operations\Learn\LearnArkCurriculum;
use App\Ark\Operations\Learn\LearnCompletion;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

function completeRequiredLearnFor(User $user): void
{
    foreach (LearnArkCurriculum::requiredArticlesFor($user) as $article) {
        LearnCompletion::query()->create([
            'user_id' => $user->id,
            'article_key' => $article['article_key'],
            'catalog_version' => LearnArkCurriculum::VERSION,
            'article_version' => LearnArkCurriculum::articleContentVersion($article['article_key']),
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now(),
        ]);
    }
}

function actingAsLearnCurrentStaff(ArkRole $role): User
{
    $user = User::factory()->create()->assignRole($role->value);
    completeRequiredLearnFor($user);

    return $user;
}

function actingAsLearnCurrentAdvisor(): User
{
    return actingAsLearnCurrentStaff(ArkRole::Advisor);
}
