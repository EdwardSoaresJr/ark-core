<?php

use App\Ark\Voice\Lab\VoiceLabRecordScore;

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
    ]);

    $this->call('POST', '/api/voice/lab/utterance', [], [], [], [
        'HTTP_X_VOICE_LAB_SECRET' => 'wrong',
        'CONTENT_TYPE' => 'audio/wav',
        'CONTENT_LENGTH' => 80,
    ], str_repeat('R', 80))->assertUnauthorized();
});

test('voice lab returns error when model provider is not configured', function (): void {
    config([
        'voice.lab_enabled' => true,
        'voice.lab_secret' => 'lab-secret',
    ]);

    $this->call('POST', '/api/voice/lab/utterance', [], [], [], [
        'HTTP_X_VOICE_LAB_SECRET' => 'lab-secret',
        'HTTP_X_VOICE_MIC' => 'sph0645',
        'HTTP_X_VOICE_EXPECT' => 'rr2-lr3',
        'CONTENT_TYPE' => 'audio/wav',
        'CONTENT_LENGTH' => 80,
    ], str_repeat('R', 80))
        ->assertStatus(502)
        ->assertJsonPath('message', 'Voice lab transcription requires a model provider (not configured).');
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
