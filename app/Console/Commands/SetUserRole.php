<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetUserRole extends Command
{
    protected $signature = 'users:set-role {email : Email address of the account} {role : user, moderator or admin}';

    protected $description = 'Change a user\'s role (the only way roles are assigned)';

    public function handle(): int
    {
        $role = UserRole::tryFrom(strtolower(trim((string) $this->argument('role'))));

        if ($role === null) {
            $this->components->error('Unknown role. Use one of: '.implode(', ', UserRole::values()).'.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($role): int {
            $user = User::query()->whereEmail((string) $this->argument('email'))->lockForUpdate()->first();

            if ($user === null) {
                $this->components->error('No user with that email address.');

                return self::FAILURE;
            }

            if ($user->role === $role) {
                $this->components->info("{$user->email} already has the {$role->value} role.");

                return self::SUCCESS;
            }

            if ($user->isAdmin() && $this->isLastAdmin($user)) {
                $this->components->error('This is the last admin. Make another user admin first.');

                return self::FAILURE;
            }

            $previous = $user->role;
            $user->forceFill(['role' => $role])->save();

            $this->components->info("{$user->email}: {$previous->value} -> {$role->value}");

            return self::SUCCESS;
        });
    }

    private function isLastAdmin(User $user): bool
    {
        return ! User::query()
            ->where('role', UserRole::Admin)
            ->whereKeyNot($user->getKey())
            ->lockForUpdate()
            ->exists();
    }
}
