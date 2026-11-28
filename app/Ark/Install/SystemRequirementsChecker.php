<?php

namespace App\Ark\Install;

use App\Ark\Runtime\InfrastructureProfile;
use Illuminate\Support\Facades\File;

final class SystemRequirementsChecker
{
    /**
     * @return list<array{id: string, label: string, status: 'pass'|'warning'|'fail', detail: string}>
     */
    public function check(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
        $checks[] = [
            'id' => 'php_version',
            'label' => 'PHP version',
            'status' => $phpOk ? 'pass' : 'fail',
            'detail' => 'PHP '.PHP_VERSION.($phpOk ? ' (requires 8.3+)' : ' - ARK requires PHP 8.3 or newer'),
        ];

        foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'] as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = [
                'id' => 'ext_'.$ext,
                'label' => 'PHP extension: '.$ext,
                'status' => $loaded ? 'pass' : 'fail',
                'detail' => $loaded ? 'Loaded' : 'Required extension missing',
            ];
        }

        $checks[] = [
            'id' => 'ext_redis',
            'label' => 'PHP extension: redis (optional)',
            'status' => extension_loaded('redis') ? 'pass' : 'warning',
            'detail' => extension_loaded('redis')
                ? 'Loaded - recommended for production cache/queues'
                : 'Not loaded - file/database drivers can be used for first run',
        ];

        foreach ([
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ] as $label => $path) {
            $writable = File::isDirectory($path) && is_writable($path);
            if (! File::isDirectory($path)) {
                @mkdir($path, 0755, true);
                $writable = is_writable($path);
            }
            $checks[] = [
                'id' => 'writable_'.md5($label),
                'label' => 'Writable: '.$label,
                'status' => $writable ? 'pass' : 'fail',
                'detail' => $writable ? 'Writable' : 'Not writable - ARK cannot persist install state or caches',
            ];
        }

        $envWriter = app(InstallerEnvironmentWriter::class);
        $checks[] = [
            'id' => 'env_mode',
            'label' => 'Bootstrap environment',
            'status' => 'pass',
            'detail' => $envWriter->mode() === 'writable'
                ? 'Writable .env - browser can persist database bootstrap values'
                : 'Immutable environment - supply APP_KEY / DB_* via the hosting platform; wizard will validate only',
        ];

        $infra = InfrastructureProfile::inspect();
        $checks[] = [
            'id' => 'infra_profile',
            'label' => 'Infrastructure profile',
            'status' => 'pass',
            'detail' => 'Detected '.$infra['profile'].' (Docker/VPS/shared availability — not a commercial entitlement)',
        ];

        $checks[] = [
            'id' => 'redis_reachability',
            'label' => 'Redis reachability',
            'status' => ! $infra['redis_configured']
                ? 'pass'
                : ($infra['redis_reachable'] ? 'pass' : 'warning'),
            'detail' => ! $infra['redis_configured']
                ? 'Redis not configured — file/database drivers are appropriate'
                : ($infra['redis_reachable']
                    ? 'Redis configured and reachable'
                    : 'Redis configured but not reachable'),
        ];

        $pdfStatus = 'fail';
        $pdfDetail = 'No PDF engine available';
        if ($infra['chromium_pdf']) {
            $pdfStatus = 'pass';
            $pdfDetail = 'Chromium PDF available';
        } elseif ($infra['native_pdf']) {
            $pdfStatus = 'pass';
            $pdfDetail = 'Native PHP PDF available';
        }
        $checks[] = [
            'id' => 'pdf_engine',
            'label' => 'PDF generation',
            'status' => $pdfStatus,
            'detail' => $pdfDetail,
        ];

        $checks[] = [
            'id' => 'install_finalize',
            'label' => 'Install finalize',
            'status' => 'pass',
            'detail' => $infra['detached_finalize']
                ? 'Detached CLI finalize available'
                : 'Constrained host — finalize runs inside the browser request with raised time limits and checkpointed progress',
        ];

        foreach ($infra['notes'] as $index => $note) {
            $checks[] = [
                'id' => 'infra_note_'.$index,
                'label' => 'Infrastructure note',
                'status' => 'warning',
                'detail' => $note,
            ];
        }

        return array_merge($checks, (new InstallationSecurityChecker)->check());
    }

    /**
     * @param  list<array{status: string}>  $checks
     */
    public function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'fail') {
                return true;
            }
        }

        return false;
    }
}
