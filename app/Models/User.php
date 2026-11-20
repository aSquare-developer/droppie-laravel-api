<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'email',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * New accounts are plain users. The role is deliberately not fillable: it is only
     * changed with php artisan users:set-role.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * Emails are stored lowercased and trimmed, so letter case never creates a second account.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : self::normalizeEmail($value),
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Email lookup. The utf8mb4 *_ci collation compares case-insensitively, so accounts stored
     * before emails were normalized are found too, and the unique index on email is used.
     */
    public function scopeWhereEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', self::normalizeEmail($email));
    }

    /**
     * The account the email and password belong to.
     */
    public static function findByCredentials(string $email, string $password): ?self
    {
        $user = static::query()->whereEmail($email)->first();

        return $user !== null && Hash::check($password, $user->password) ? $user : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Admins can do everything moderators can.
     */
    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator || $this->isAdmin();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function activeVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->where('is_active', true);
    }

    /**
     * The vehicle new routes are recorded for.
     *
     * @throws ValidationException when the user has no active vehicle
     */
    public function requireActiveVehicle(): Vehicle
    {
        return $this->activeVehicle()->first() ?? throw ValidationException::withMessages([
            'vehicle' => 'Add an active vehicle to the profile before creating a route.',
        ]);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function tripReports(): HasMany
    {
        return $this->hasMany(TripReport::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function addressUsages(): HasMany
    {
        return $this->hasMany(AddressUsage::class);
    }
}
