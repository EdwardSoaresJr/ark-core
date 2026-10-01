<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class OutboundComposerFiles
{
    /**
     * @return list<UploadedFile>
     */
    public static function from(Request $request): array
    {
        $files = [];
        $single = $request->file('attachment');
        if ($single instanceof UploadedFile) {
            $files[] = $single;
        }

        $many = $request->file('attachments');
        if ($many instanceof UploadedFile) {
            $many = [$many];
        }

        if (is_array($many)) {
            foreach ($many as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        return array_values($files);
    }
}
