<?php

use App\Ark\Voice\Lab\VoiceLabRecordScore;
use Illuminate\Support\Facades\Http;

test('voice lab is absent when disabled', function (): void {
    config([
        'voice.lab_enabled' => false,
        'voice.lab_secret' => 'lab-secret',
    ]);

    $this->post('/api/voice/lab/utterance', [], [
        'X-Voice-Lab-Secret' => 'lab-secret',
    ])->assertNotFound();
});

test('voice lab rejects a bad secret', function (): void {
    config([
        'voice.lab_enabled' => true,
        'voice.lab_secret' => 'lab-secret',
        'dragon.openai_api_key' => 'sk-test',
    ]);

    $this->call('POST', '/api/voice/lab/utterance', [], [], [], [
        'HTTP_X_VOICE_LAB_SECRET' => 'wrong',
        'CONTENT_TYPE' => 'audio/wav',
        'CONTENT_LENGTH' => 80,
    ], str_repeat('R', 80))->assertUnauthorized();
});

test('voice lab transcribes wav without storing or calling dragon', function (): void {
    config([
        'voice.lab_enabled' => true,
        'voice.lab_secret' => 'lab-secret',
        'dragon.openai_api_key' => 'sk-test',
    ]);

    Http::fake([
        'https://api.openai.com/v1/audio/transcriptions' => Http::response('Right rear two millimeters, left rear three.', 200),
    ]);

    $this->call('POST', '/api/voice/lab/utterance', [], [], [], [
        'HTTP_X_VOICE_LAB_SECRET' => 'lab-secret',
        'HTTP_X_VOICE_MIC' => 'sph0645',
        'HTTP_X_VOICE_EXPECT' => 'rr2-lr3',
        'CONTENT_TYPE' => 'audio/wav',
        'CONTENT_LENGTH' => 80,
    ], str_repeat('R', 80))
        ->assertOk()
        ->assertJsonPath('transcript', 'Right rear two millimeters, left rear three.')
        ->assertJsonPath('stored', false)
        ->assertJsonPath('dragon', false)
        ->assertJsonPath('score.record_accurate', true)
        ->assertJsonPath('score.laterality_swap_suspected', false);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'audio/transcriptions'));
});

test('swapped laterality is a record fail even when conversational overlap is true', function (): void {
    $score = new VoiceLabRecordScore;

    $result = $score->score(
        VoiceLabRecordScore::goldRearPadFacts(),
        'Right rear three millimeters, left rear two.',
    );

    expect($result['conversational_ok'])->toBeTrue()
        ->and($result['record_accurate'])->toBeFalse()
        ->and($result['laterality_swap_suspected'])->toBeTrue();
});
