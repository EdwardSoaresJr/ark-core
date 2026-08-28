<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordStaffFrontDoorLanding
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof User && $request->isMethod('GET') && $response->isSuccessful()) {
            $sessionKey = 'ark:front_door_landed';

            if (! $request->session()->has($sessionKey)) {
                $this->events->record(
                    OperationalEventName::StaffFrontDoorLanded,
                    User::class,
                    $user->id,
                    $user,
                    ['surface' => $surface],
                );

                $request->session()->put($sessionKey, $surface);
            }
        }

        return $response;
    }
}
