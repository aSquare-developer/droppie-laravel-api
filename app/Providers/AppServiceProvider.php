<?php

namespace App\Providers;

use App\Models\User;
use App\Services\FailureAlerter;
use App\Support\RetriedJobExceptions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewAdmin', fn (User $user): bool => $user->isAdmin());
        // Moderators and admins. No moderator features exist yet.
        Gate::define('moderate', fn (User $user): bool => $user->isModerator());

        // Jobs that have used up their retries.
        Event::listen(JobFailed::class, fn (JobFailed $event) => $this->app->make(FailureAlerter::class)->jobFailed($event));
        // Attempts that will be retried stay out of Sentry; see SentryScrubber::beforeSend().
        Event::listen(JobExceptionOccurred::class, [RetriedJobExceptions::class, 'remember']);

        // One password policy for every place that sets a password. The breach check sends only
        // a hash prefix to Have I Been Pwned and passes if the service cannot be reached.
        Password::defaults(function (): Password {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request): array => [
            Limit::perHour(5)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        RateLimiter::for('address-autocomplete', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('address-validation', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('route-write', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateLimitKey($request)));
    }

    private function rateLimitKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}
