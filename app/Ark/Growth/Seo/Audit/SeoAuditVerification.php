<?php

namespace App\Ark\Growth\Seo\Audit;

final class SeoAuditVerification
{
    public static function atRequestTime(): string
    {
        return now()->toIso8601String();
    }

    public static function deploymentLabel(): string
    {
        $ref = config('growth.audit.deployment_ref')
            ?? env('APP_DEPLOY_REF')
            ?? self::gitShortSha();

        if ($ref === null || $ref === '') {
            return 'Evaluated from application configuration at request time';
        }

        return 'Deployment '.$ref;
    }

    private static function gitShortSha(): ?string
    {
        $base = base_path();

        if (! is_dir($base.'/.git')) {
            return null;
        }

        $sha = trim((string) @shell_exec('git -C '.escapeshellarg($base).' rev-parse --short HEAD 2>/dev/null'));

        return $sha !== '' ? $sha : null;
    }
}
