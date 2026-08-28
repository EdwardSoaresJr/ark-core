<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\MobileUserPresenter;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class MobileAuthLoginController
{
    public function __invoke(Request $request, MobileStaffAccess $access, MobileUserPresenter $presenter): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled.'],
            ]);
        }

        if ($user->hasRole(ArkRole::Customer->value)) {
            throw ValidationException::withMessages([
                'email' => ['This account cannot sign in to ARK Mobile.'],
            ]);
        }

        if (! $access->canUseMobile($user)) {
            throw ValidationException::withMessages([
                'email' => ['Mobile access is not enabled for this account. In Settings → Staff, assign Admin, Advisor, or Technician. Technicians sign in to My Work only.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            ...$presenter->presentShell($user),
        ]);
    }
}
