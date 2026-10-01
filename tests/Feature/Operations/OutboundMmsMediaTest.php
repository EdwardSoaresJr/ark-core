<?php

use App\Ark\Operations\Messaging\OutboundAttachmentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('outbound mms media route serves signed attachment without auth', function () {
    Storage::fake('local');

    $store = app(OutboundAttachmentStore::class);
    $stored = $store->store(UploadedFile::fake()->image('brake-pad.jpg'));

    $this->get($stored['public_url'])
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    expect(Storage::disk('local')->exists($stored['storage_path']))->toBeTrue()
        ->and($stored['public_url'])->not->toMatch('/\.(jpe?g|png|gif|webp|mp4|pdf)\?/');
});

test('outbound mms media route rejects unsigned requests', function () {
    Storage::fake('local');

    $mediaToken = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    Storage::disk('local')->put(
        OutboundAttachmentStore::STORAGE_DIRECTORY.'/'.$mediaToken.'.jpg',
        UploadedFile::fake()->image('leak.jpg')->getContent(),
    );

    $unsignedUrl = URL::route('messaging.outbound-media', [
        'token' => $mediaToken,
    ]);

    $this->get($unsignedUrl)->assertForbidden();
});
