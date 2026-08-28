<?php

namespace App\Ark\Tech\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\MobileUserPresenter;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Tech\TechStaffGate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class TechLoginController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $mobileAccess,
        MobileUserPresenter $presenter,
        TechStaffGate $gate,
    ): JsonResponse {
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

        if ($user->hasRole(ArkRole::Customer->value) || ! $mobileAccess->canUseMobile($user)) {
            throw ValidationException::withMessages([
                'email' => ['This account cannot sign in to ARK Tech.'],
            ]);
        }

        if (! $gate->canUseTech($user)) {
            throw ValidationException::withMessages([
                'email' => ['ARK Tech is for technicians. Advisors use ARK or Shop Glass.'],
            ]);
        }

        $token = $user->createToken('ark-tech:'.$credentials['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'product' => 'ark_tech',
            ...$presenter->presentShell($user),
        ]);
    }
}
