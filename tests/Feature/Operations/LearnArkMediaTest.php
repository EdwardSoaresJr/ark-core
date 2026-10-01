<?php

use App\Ark\Operations\Learn\LearnArticleMedia;
use App\Ark\Operations\Learn\LearnArticleKey;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can upload learn guide image', function () {
    Storage::fake('local');
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $file = UploadedFile::fake()->image('intake-recognition-band.png', 800, 450);

    $this->actingAs($admin)
        ->post(route('operations.learn.media.store', ['role' => 'advisor', 'article' => 'advisor-intake']), [
            'slot' => 'intake-recognition-band.png',
            'kind' => 'image',
            'file' => $file,
        ])
        ->assertRedirect(route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']))
        ->assertSessionHas('learn_media_saved');

    $media = LearnArticleMedia::query()->first();

    expect($media)->not->toBeNull()
        ->and($media->article_key)->toBe('advisor:advisor-intake')
        ->and($media->kind)->toBe('image');

    Storage::disk('local')->assertExists($media->storage_path);

    $this->actingAs($admin)
        ->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']))
        ->assertOk()
        ->assertSee(route('operations.learn.media.show', $media), false);
});

test('admin can embed youtube video for learn guide', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->post(route('operations.learn.media.store', ['role' => 'advisor', 'article' => 'advisor-intake']), [
            'slot' => 'video:main',
            'kind' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertRedirect()
        ->assertSessionHas('learn_media_saved');

    $this->actingAs($admin)
        ->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']))
        ->assertOk()
        ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
});

test('advisor cannot upload learn guide media', function () {
    Storage::fake('local');
    $this->seed(ArkAuthorizationSeeder::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $file = UploadedFile::fake()->image('shot.png');

    $this->actingAs($advisor)
        ->post(route('operations.learn.media.store', ['role' => 'advisor', 'article' => 'advisor-intake']), [
            'slot' => 'shot.png',
            'kind' => 'image',
            'file' => $file,
        ])
        ->assertForbidden();

    expect(LearnArticleMedia::query()->count())->toBe(0);
});

test('admin can delete learn guide media', function () {
    Storage::fake('local');
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $file = UploadedFile::fake()->image('intake-recognition-band.png');

    $this->actingAs($admin)
        ->post(route('operations.learn.media.store', ['role' => 'advisor', 'article' => 'advisor-intake']), [
            'slot' => 'intake-recognition-band.png',
            'kind' => 'image',
            'file' => $file,
        ]);

    $media = LearnArticleMedia::query()->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('operations.learn.media.destroy', [
            'role' => 'advisor',
            'article' => 'advisor-intake',
            'media' => $media,
        ]))
        ->assertRedirect()
        ->assertSessionHas('learn_media_saved');

    expect(LearnArticleMedia::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($media->storage_path);
});

test('learn youtube parser accepts common url formats', function () {
    expect(\App\Ark\Operations\Learn\LearnYoutubeVideoId::parse('dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(\App\Ark\Operations\Learn\LearnYoutubeVideoId::parse('https://youtu.be/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(\App\Ark\Operations\Learn\LearnYoutubeVideoId::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=12s'))->toBe('dQw4w9WgXcQ')
        ->and(\App\Ark\Operations\Learn\LearnYoutubeVideoId::parse('not-a-link'))->toBeNull();
});
