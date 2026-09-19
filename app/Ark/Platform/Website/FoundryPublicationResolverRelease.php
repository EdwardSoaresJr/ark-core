<?php

namespace App\Ark\Platform\Website;

final class FoundryPublicationResolverRelease
{
    public const IMAGE_DIGEST = 'sha256:53f825b4ca2853537103aae10c051999009f1ddbdd1f1404f3d49be928d5d85b';

    public static function matches(string $digest): bool
    {
        return hash_equals(self::IMAGE_DIGEST, $digest);
    }
}
