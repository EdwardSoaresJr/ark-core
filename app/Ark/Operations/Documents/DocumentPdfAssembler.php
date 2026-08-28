<?php

namespace App\Ark\Operations\Documents;

use Imagick;
use ImagickException;
use InvalidArgumentException;
use RuntimeException;

/** Assembles captured page images into one PDF. */
final class DocumentPdfAssembler
{
    /**
     * @param  list<string>  $absoluteImagePaths
     */
    public function assemble(array $absoluteImagePaths): string
    {
        if ($absoluteImagePaths === []) {
            throw new InvalidArgumentException('At least one page is required.');
        }

        if (! class_exists(Imagick::class)) {
            throw new RuntimeException('Document scan requires Imagick on this host.');
        }

        $output = sys_get_temp_dir().'/ark-doc-'.uniqid('', true).'.pdf';
        $pdf = new Imagick;

        try {
            foreach ($absoluteImagePaths as $path) {
                if (! is_file($path)) {
                    throw new InvalidArgumentException('A scan page is missing.');
                }

                $page = new Imagick;
                try {
                    $page->readImage($path);
                    $page->setImageFormat('pdf');
                    $pdf->addImage($page);
                } catch (ImagickException $e) {
                    throw new InvalidArgumentException(
                        'One of the scan pages could not be read. Use JPG, PNG, or HEIC.',
                        0,
                        $e,
                    );
                } finally {
                    $page->clear();
                    $page->destroy();
                }
            }

            $pdf->setImageFormat('pdf');
            $pdf->writeImages($output, true);
        } finally {
            $pdf->clear();
            $pdf->destroy();
        }

        if (! is_file($output)) {
            throw new RuntimeException('Failed to assemble document PDF.');
        }

        return $output;
    }
}
