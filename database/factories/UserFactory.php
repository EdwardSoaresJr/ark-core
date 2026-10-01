<?php

namespace Database\Factories;

use App\Ark\Operations\Staff\StaffInvitationIssuer;
use App\Ark\Runtime\Preferences\AccentTheme;
use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'accent_theme' => AccentTheme::Ark2->value,
            'display_theme' => DisplayTheme::default()->value,
            'email_verified_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->password ??= static::$password ??= Hash::make('password');
            $user->remember_token ??= Str::random(10);
            $user->is_active ??= true;
            $user->password_set_at ??= now();
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->is_active = false;
        });
    }

    public function awaitingInvitation(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->password_set_at = null;
            $user->password = StaffInvitationIssuer::placeholderPassword();
        });
    }
}
