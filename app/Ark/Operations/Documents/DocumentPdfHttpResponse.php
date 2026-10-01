<?php

namespace App\Ark\Operations\Documents;

use Illuminate\Http\Response;

final class DocumentPdfHttpResponse
{
    public static function inline(string $contents, string $filename): Response
    {
        return response($contents, 200, self::headers('inline', $filename, $contents));
    }

    public static function attachment(string $contents, string $filename): Response
    {
        return response($contents, 200, self::headers('attachment', $filename, $contents));
    }

    /**
     * @return array<string, string>
     */
    private static function headers(string $disposition, string $filename, string $contents): array
    {
        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'ETag' => '"'.sha1($contents).'"',
        ];
    }
}
