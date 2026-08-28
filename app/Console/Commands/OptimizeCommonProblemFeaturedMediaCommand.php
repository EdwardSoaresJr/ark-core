<?php

namespace App\Console\Commands;

use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMediaOptimizer;
use App\Ark\Operations\Leads\Public\ShopPublicSurfaceRaw;
use Illuminate\Console\Command;

class OptimizeCommonProblemFeaturedMediaCommand extends Command
{
    protected $signature = 'ark:public-surface:optimize-common-problem-media
        {--only-missing : Skip files already stored as optimized WebP variants}
        {--slug= : Optimize a single common-problem slug}';

    protected $description = 'Resize and convert common-problem featured media to WebP display and lightbox variants.';

    public function handle(): int
    {
        if (! extension_loaded('gd') || (! CommonProblemFeaturedMediaOptimizer::supportsWebp() && ! function_exists('imagejpeg'))) {
            $this->components->error('GD with JPEG or WebP support is required for image optimization.');

            return self::FAILURE;
        }

        $onlyMissing = (bool) $this->option('only-missing');
        $slugFilter = trim((string) $this->option('slug'));
        $stored = CommonProblemFeaturedMedia::allStored();
        $updatedMap = [];
        $optimized = 0;
        $skipped = 0;

        foreach ($stored as $slug => $gallery) {
            if ($slugFilter !== '' && $slug !== $slugFilter) {
                continue;
            }

            $updatedGallery = [];

            foreach ($gallery as $item) {
                $path = $item['path'];

                if ($onlyMissing && CommonProblemFeaturedMediaOptimizer::isDisplayPath($path)) {
                    $updatedGallery[] = $item;
                    $skipped++;

                    continue;
                }

                $result = CommonProblemFeaturedMediaOptimizer::optimizeStoredPath($path);

                if ($result === null) {
                    $updatedGallery[] = $item;
                    $skipped++;

                    continue;
                }

                $updatedGallery[] = [
                    ...$item,
                    'path' => $result['display'],
                ];
                $optimized++;
            }

            if ($updatedGallery !== []) {
                $updatedMap[$slug] = $updatedGallery;
            }
        }

        if ($updatedMap !== []) {
            $raw = ShopPublicSurfaceRaw::read();
            $raw['common_problem_featured_media'] = array_replace(
                is_array($raw['common_problem_featured_media'] ?? null) ? $raw['common_problem_featured_media'] : [],
                $updatedMap,
            );
            ShopPublicSurfaceRaw::write($raw);
        }

        $this->components->info(sprintf(
            'Optimized %d image(s); skipped %d.',
            $optimized,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
