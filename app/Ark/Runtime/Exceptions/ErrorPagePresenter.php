<?php

namespace App\Ark\Runtime\Exceptions;

use App\Ark\Operations\Staff\StaffFrontDoor;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ErrorPagePresenter
{
    /**
     * @return array{
     *     code: int,
     *     title: string,
     *     message: string,
     *     primary_label: string,
     *     primary_url: string,
     *     secondary_label: string,
     *     secondary_url: string,
     *     surface: string,
     * }
     */
    public static function forStatus(int $status, ?Request $request = null): array
    {
        $request ??= request();
        $surface = self::surface($request);
        $navigation = self::navigation($surface, $request);
        $copy = self::copy($status, $surface);

        return [
            'code' => $status,
            'title' => $copy['title'],
            'message' => $copy['message'],
            'primary_label' => $navigation['primary_label'],
            'primary_url' => $navigation['primary_url'],
            'secondary_label' => $navigation['secondary_label'],
            'secondary_url' => $navigation['secondary_url'],
            'surface' => $surface,
        ];
    }

    /**
     * @return array{
     *     code: int,
     *     title: string,
     *     message: string,
     *     primary_label: string,
     *     primary_url: string,
     *     secondary_label: string,
     *     secondary_url: string,
     *     surface: string,
     * }
     */
    public static function forException(HttpExceptionInterface $exception, ?Request $request = null): array
    {
        $presented = self::forStatus($exception->getStatusCode(), $request);

        if ($exception->getMessage() !== '' && ! self::usesDefaultMessage($exception->getStatusCode(), $exception->getMessage())) {
            $presented['message'] = $exception->getMessage();
        }

        return $presented;
    }

    protected static function surface(Request $request): string
    {
        if ($request->is('app', 'app/*')) {
            return 'operations';
        }

        if ($request->is('portal', 'portal/*')) {
            return 'portal';
        }

        return 'public';
    }

    /**
     * @return array{primary_label: string, primary_url: string, secondary_label: string, secondary_url: string}
     */
    protected static function navigation(string $surface, Request $request): array
    {
        $previous = url()->previous();
        $fallbackSecondary = $previous !== $request->fullUrl() ? $previous : url('/');

        return match ($surface) {
            'operations' => [
                'primary_label' => 'Return To Workboard',
                'primary_url' => StaffFrontDoor::landingUrl($request->user()),
                'secondary_label' => 'Go Back',
                'secondary_url' => $fallbackSecondary,
            ],
            'portal' => [
                'primary_label' => 'Return To Portal',
                'primary_url' => route('portal.index'),
                'secondary_label' => 'Go Back',
                'secondary_url' => $fallbackSecondary,
            ],
            default => [
                'primary_label' => 'Return Home',
                'primary_url' => url('/'),
                'secondary_label' => auth()->check() ? 'Go Back' : 'Staff Login',
                'secondary_url' => auth()->check() ? $fallbackSecondary : route('login'),
            ],
        };
    }

    /**
     * @return array{title: string, message: string}
     */
    protected static function copy(int $status, string $surface): array
    {
        $shopLabel = match ($surface) {
            'portal' => 'portal',
            'operations' => 'worksheet',
            default => 'page',
        };

        return match ($status) {
            401 => [
                'title' => 'Sign in required',
                'message' => 'Your session ended or this area requires staff sign-in.',
            ],
            403 => [
                'title' => 'Access not allowed',
                'message' => 'You do not have permission to open this '.$shopLabel.'.',
            ],
            404 => [
                'title' => 'Page not found',
                'message' => 'That link is missing or may have moved.',
            ],
            419 => [
                'title' => 'Session expired',
                'message' => 'Your session timed out. Refresh the page and try again.',
            ],
            429 => [
                'title' => 'Too many requests',
                'message' => 'Please wait a moment before trying again.',
            ],
            503 => [
                'title' => 'Temporarily unavailable',
                'message' => 'The shop system is briefly down for maintenance. Try again shortly.',
            ],
            default => $status >= 500
                ? [
                    'title' => 'Something went wrong',
                    'message' => 'An unexpected error occurred. The shop has been notified so we can fix it.',
                ]
                : [
                    'title' => 'Request could not be completed',
                    'message' => 'This request could not be completed. Try again or return to your previous screen.',
                ],
        };
    }

    protected static function usesDefaultMessage(int $status, string $message): bool
    {
        $normalized = strtolower(trim($message));

        return in_array($normalized, [
            'not found.',
            'not found',
            'forbidden.',
            'forbidden',
            'unauthorized.',
            'unauthorized',
            'page expired',
            'too many requests',
            'service unavailable',
            'server error',
            'bad request.',
            'bad request',
        ], true) || ($status === 404 && str_contains($normalized, 'no query results'));
    }
}
