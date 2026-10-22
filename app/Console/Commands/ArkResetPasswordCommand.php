<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Local account recovery for a standalone install: needs shell access to the server, no mail, and no ARK service.
 */
class ArkResetPasswordCommand extends Command
{
    protected $signature = 'ark:reset-password {email : The staff member\'s sign-in email}';

    protected $description = 'Set a new password for a staff account from the server, without email or any ARK service.';

    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No staff account uses that email.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $confirmation = (string) $this->secret('Repeat the new password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $password,
            'password_set_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database' && Schema::hasTable((string) config('session.table', 'sessions'))) {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        $this->info("Password updated for {$user->email}. Existing sessions were signed out.");

        return self::SUCCESS;
    }
}
