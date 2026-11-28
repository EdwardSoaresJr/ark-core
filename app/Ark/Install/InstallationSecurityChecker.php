<?php

namespace App\Ark\Install;

use Illuminate\Support\Facades\File;

/**
 * Hosting security posture checks for traditional / shared installs.
 *
 * @return list of check rows compatible with SystemRequirementsChecker.
 */
final class InstallationSecurityChecker
{
    /**
     * @return list<array{id: string, label: string, status: 'pass'|'warning'|'fail', detail: string}>
     */
    public function check(): array
    {
        $checks = [];

        $docroot = public_path();
        $checks[] = [
            'id' => 'document_root',
            'label' => 'Document root',
            'status' => str_ends_with(rtrim($docroot, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR.'public') ? 'pass' : 'warning',
            'detail' => 'Web server document root must be the public/ directory (currently '.$docroot.')',
        ];

        $sensitive = [
            base_path('.env'),
            base_path('composer.json'),
            storage_path('app'),
            base_path('vendor/autoload.php'),
        ];
        foreach ($sensitive as $path) {
            $basename = basename($path);
            $checks[] = [
                'id' => 'sensitive_'.$basename,
                'label' => 'Protected path: '.$basename,
                'status' => File::exists($path) ? 'pass' : 'warning',
                'detail' => File::exists($path)
                    ? 'Present outside the public document root — keep the vhost rooted at public/'
                    : 'Expected path missing: '.$path,
            ];
        }

        $publicEnv = public_path('.env');
        $checks[] = [
            'id' => 'public_env_absent',
            'label' => 'No .env under public/',
            'status' => File::exists($publicEnv) ? 'fail' : 'pass',
            'detail' => File::exists($publicEnv)
                ? 'Refuse to continue: .env must never be web-accessible under public/'
                : 'No .env under public/',
        ];

        $storageLink = public_path('storage');
        $checks[] = [
            'id' => 'storage_link',
            'label' => 'Public storage link',
            'status' => is_link($storageLink) || File::isDirectory($storageLink) ? 'pass' : 'warning',
            'detail' => 'public/storage should link to storage/app/public for approved uploads only',
        ];

        $https = request()?->secure() || str_starts_with((string) config('app.url'), 'https://');
        $checks[] = [
            'id' => 'https',
            'label' => 'HTTPS',
            'status' => $https ? 'pass' : 'warning',
            'detail' => $https
                ? 'HTTPS appears configured'
                : 'Configure HTTPS before production use; APP_URL should start with https://',
        ];

        $checks[] = [
            'id' => 'setup_lock',
            'label' => 'Installer lock after install',
            'status' => 'pass',
            'detail' => InstallationState::isInstalled()
                ? 'Setup routes are locked (EnsureSetupAllowed)'
                : 'Setup remains available until installation completes; takeover protected by InstallationMutex',
        ];

        return $checks;
    }
}
