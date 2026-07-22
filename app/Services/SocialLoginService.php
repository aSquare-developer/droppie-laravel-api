<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser as SocialiteUser;
use UnexpectedValueException;

class SocialLoginService
{
    public function resolveGoogleUser(SocialiteUser $googleUser): User
    {
        $providerUserId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawUser = $googleUser->getRaw();
        $emailVerified = $rawUser['verified_email'] ?? $rawUser['email_verified'] ?? false;

        if ($providerUserId === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UnexpectedValueException('Google did not return the required account details.');
        }

        if (! filter_var($emailVerified, FILTER_VALIDATE_BOOL)) {
            throw new UnexpectedValueException('Google did not verify this email address.');
        }

        return DB::transaction(function () use ($googleUser, $providerUserId, $email, $rawUser): User {
            $socialAccount = SocialAccount::query()
                ->with('user')
                ->where('provider', 'google')
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($socialAccount !== null) {
                return $socialAccount->user;
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(64)),
                ])->save();
            } elseif ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            [$firstName, $lastName] = $this->nameParts($googleUser, $rawUser);

            $user->profile()->firstOrCreate([], [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
            $user->vehicles()->firstOrCreate(['is_active' => true]);
            $user->socialAccounts()->updateOrCreate([
                'provider' => 'google',
            ], [
                'provider_user_id' => $providerUserId,
            ]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $rawUser
     * @return array{0: string, 1: string|null}
     */
    private function nameParts(SocialiteUser $googleUser, array $rawUser): array
    {
        $firstName = trim((string) ($rawUser['given_name'] ?? ''));
        $lastName = trim((string) ($rawUser['family_name'] ?? ''));

        if ($firstName === '') {
            $fullName = trim((string) $googleUser->getName());
            $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
            $firstName = trim($nameParts[0] ?? '') ?: 'User';
            $lastName = $lastName !== '' ? $lastName : ($nameParts[1] ?? '');
        }

        return [
            Str::limit($firstName, 255, ''),
            $lastName !== '' ? Str::limit($lastName, 255, '') : null,
        ];
    }
}
