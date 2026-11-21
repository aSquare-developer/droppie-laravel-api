<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Tracing\Span;
use Sentry\UserDataBag;
use Throwable;

/**
 * Keeps secrets and personal data out of everything sent to Sentry: passwords, Sanctum
 * tokens, Google API keys, cookies and SQL values. The user is identified by id only.
 */
class SentryScrubber
{
    public const FILTERED = '[Filtered]';

    /** Keys whose values are always replaced, wherever they appear. */
    private const SENSITIVE_KEY = '/pass(word)?|secret|token|api[_-]?key|^key$|authori[sz]ation|cookie|session|csrf|xsrf|credential|dsn/i';

    /** @var array<string, string> pattern => replacement */
    private const SENSITIVE_TEXT = [
        // Query parameters such as Google's ?key=..., session tokens and signatures.
        // The start of the string counts too, for a bare query string such as "token=...&page=2".
        '/((?:^|[?&])(?:key|api_?key|[a-z_]*token|signature|password|secret)=)[^&#\s"\']*/i' => '$1'.self::FILTERED,
        // Google API keys anywhere in a string.
        '/AIza[0-9A-Za-z_\-]{35}/' => self::FILTERED,
        // Bearer tokens, including Sanctum's "id|token" format.
        '/(Bearer\s+)[^\s"\']+/i' => '$1'.self::FILTERED,
    ];

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        // Jobs that will be retried are reported once they have used up their retries.
        if ($hint?->exception instanceof Throwable && RetriedJobExceptions::willBeRetried($hint->exception)) {
            return null;
        }

        self::scrubEvent($event);

        $event->setExceptions(array_map(function (ExceptionDataBag $exception): ExceptionDataBag {
            return $exception->setValue(self::scrubText($exception->getValue()));
        }, $event->getExceptions()));

        if ($event->getMessage() !== null) {
            $event->setMessage(self::scrubText($event->getMessage()));
        }

        return $event;
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): ?Event
    {
        self::scrubEvent($event);

        $event->setSpans(array_map(function (Span $span): Span {
            $span->setDescription($span->getDescription() === null ? null : self::scrubText($span->getDescription()));
            $span->setData(self::scrubArray($span->getData()));

            return $span;
        }, $event->getSpans()));

        if ($event->getTransaction() !== null) {
            $event->setTransaction(self::scrubText($event->getTransaction()));
        }

        return $event;
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        if ($breadcrumb->getMessage() !== null) {
            $breadcrumb = $breadcrumb->withMessage(self::scrubText($breadcrumb->getMessage()));
        }

        foreach (self::scrubArray($breadcrumb->getMetadata()) as $name => $value) {
            $breadcrumb = $breadcrumb->withMetadata((string) $name, $value);
        }

        return $breadcrumb;
    }

    private static function scrubEvent(Event $event): void
    {
        $request = $event->getRequest();
        unset($request['data'], $request['cookies'], $request['env']);
        $event->setRequest(self::scrubArray($request));

        $event->setExtra(self::scrubArray($event->getExtra()));
        $event->setTags(self::scrubArray($event->getTags()));

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext((string) $name, self::scrubArray($context));
        }

        $event->setBreadcrumb(array_values(array_filter(array_map(
            fn (Breadcrumb $breadcrumb): ?Breadcrumb => self::beforeBreadcrumb($breadcrumb),
            $event->getBreadcrumbs(),
        ))));

        $event->setUser(self::userWithIdOnly($event->getUser()));
    }

    private static function userWithIdOnly(?UserDataBag $user): ?UserDataBag
    {
        $id = null;

        try {
            // hasUser() does not resolve a user, so a queue job or a guest stays anonymous.
            if (Auth::hasUser()) {
                $id = Auth::id();
            }
        } catch (Throwable) {
            // No auth in this context (early boot, console).
        }

        $id ??= $user?->getId();

        return $id === null ? null : new UserDataBag($id);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1) {
                $data[$key] = self::FILTERED;
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = self::scrubText($value);
            }
        }

        return $data;
    }

    public static function scrubText(string $text): string
    {
        $text = (string) preg_replace(array_keys(self::SENSITIVE_TEXT), array_values(self::SENSITIVE_TEXT), $text);

        // Query exceptions quote the SQL with its values (emails, password and token hashes).
        if (str_contains($text, 'SQL: ')) {
            $text = (string) preg_replace_callback(
                '/SQL: .*/s',
                fn (array $match): string => (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/", '?', $match[0]),
                $text,
            );
        }

        return $text;
    }
}
