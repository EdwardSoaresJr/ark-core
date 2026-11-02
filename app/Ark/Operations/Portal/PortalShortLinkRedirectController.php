<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class PortalShortLinkRedirectController
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $link = PortalShortLink::query()
            ->where('code', $code)
            ->first();

        if ($link === null || ! $link->isActive()) {
            abort(404);
        }

        return redirect()->to(self::onThisInstallation($request, (string) $link->destination_url));
    }

    /**
     * A stored destination is an absolute URL on whatever host the installation answered on when the link
     * was made. When its path is one of this installation's portal pages, the customer is sent there on the
     * current host, so links keep working after the shop changes domains. Anything else is left as stored.
     */
    private static function onThisInstallation(Request $request, string $destination): string
    {
        $parts = parse_url($destination);
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || ! isset($parts['host'])) {
            return $destination;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($request->getSchemeAndHttpHost().$path, 'GET'));
        } catch (Throwable) {
            return $destination;
        }

        $name = (string) $route->getName();
        if (! str_starts_with($name, 'portal.') || $name === 'portal.short.redirect') {
            return $destination;
        }

        return url($path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : ''));
    }
}
