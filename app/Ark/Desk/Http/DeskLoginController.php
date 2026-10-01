<?php

namespace App\Ark\Desk\Http;

use App\Ark\Desk\DeskWorkProjection;
use App\Ark\Mobile\MobileUserPresenter;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class DeskLoginController
{
    public function __invoke(Request $request, MobileUserPresenter $presenter, DeskWorkProjection $work): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
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

        if (! $user->hasAnyRole([ArkRole::Admin->value, ArkRole::Advisor->value])) {
            throw ValidationException::withMessages([
                'email' => ['ARK Desk is for advisors and admins.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'ARK Desk')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'product' => 'ark_desk',
            'shop' => $work->shopContext(),
            ...$presenter->presentShell($user),
        ]);
    }
}
