<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('dashboard:user')]
#[Description('Interactively provision the single dashboard account')]
class ProvisionDashboardUser extends Command
{
    public function handle(): int
    {
        if (User::exists()) {
            $this->error('An account already exists. This dashboard supports only one user.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Name'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        return DB::transaction(function () use ($data): int {
            if (User::exists()) {
                $this->error('An account was provisioned while this command was running.');

                return self::FAILURE;
            }

            $user = new User;
            $user->id = 1;
            $user->fill(collect($data)->except('password_confirmation')->all())->save();
            $this->info('Account created. Sign in at /admin.');

            return self::SUCCESS;
        });
    }
}
